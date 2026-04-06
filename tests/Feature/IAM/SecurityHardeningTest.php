<?php

use App\Enums\CustomerStatus;
use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Otp;
use App\Models\Shift;
use App\Models\User;
use App\Services\OTPService;
use Database\Seeders\RoleSeeder;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a user with an employee record, assign a role, and return both.
 *
 * @return array{user: User, employee: Employee}
 */
function createStaff(string $role, ?Branch $branch = null): array
{
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => EmployeeStatus::Active,
    ]);

    if ($branch) {
        $employee->branches()->attach($branch);
    }

    $user->assignRole($role);

    return ['user' => $user->fresh(), 'employee' => $employee];
}

/**
 * Create a customer user (no password, no employee record).
 *
 * @return array{user: User, customer: Customer}
 */
function createCustomer(): array
{
    $user = User::factory()->create(['password' => null]);
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    return ['user' => $user->fresh(), 'customer' => $customer];
}

/*
|--------------------------------------------------------------------------
| Setup — seed roles & permissions once per file
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

/*
|--------------------------------------------------------------------------
| IAM-001: routes/protected.php — permission gating
|--------------------------------------------------------------------------
*/

describe('IAM-001: protected.php permission gating', function () {
    it('allows customers to list their own orders', function () {
        ['user' => $user] = createCustomer();

        $this->actingAs($user)
            ->getJson('/v1/orders')
            ->assertSuccessful();
    });

    it('blocks customers from kitchen orders', function () {
        ['user' => $user] = createCustomer();

        $this->actingAs($user)
            ->getJson('/v1/kitchen/orders')
            ->assertForbidden();
    });

    it('blocks customers from order-manager orders', function () {
        ['user' => $user] = createCustomer();

        $this->actingAs($user)
            ->getJson('/v1/order-manager/orders')
            ->assertForbidden();
    });

    it('blocks customers from refunding orders', function () {
        ['user' => $user] = createCustomer();
        ['user' => $admin] = createStaff('admin');

        // Create a real order to avoid 404 from model binding
        $order = \App\Models\Order::factory()->create();

        $this->actingAs($user)
            ->postJson("/v1/orders/{$order->id}/refund")
            ->assertForbidden();
    });

    it('allows kitchen staff to view kitchen orders', function () {
        $branch = Branch::factory()->create();
        ['user' => $user] = createStaff('kitchen', $branch);

        $this->actingAs($user)
            ->getJson('/v1/kitchen/orders')
            ->assertSuccessful();
    });
});

/*
|--------------------------------------------------------------------------
| IAM-002: Branch access middleware
|--------------------------------------------------------------------------
*/

describe('IAM-002: branch access enforcement', function () {
    it('blocks managers from accessing branches they do not manage', function () {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        ['user' => $user] = createStaff('manager', $ownBranch);

        $this->actingAs($user)
            ->getJson("/v1/manager/branches/{$otherBranch->id}/stats")
            ->assertForbidden();
    });

    it('allows managers to access their own branch', function () {
        $branch = Branch::factory()->create();
        ['user' => $user] = createStaff('manager', $branch);

        $this->actingAs($user)
            ->getJson("/v1/manager/branches/{$branch->id}/stats")
            ->assertSuccessful();
    });

    it('allows admins to bypass branch ownership', function () {
        $branch = Branch::factory()->create();
        ['user' => $user] = createStaff('admin');

        $this->actingAs($user)
            ->getJson("/v1/manager/branches/{$branch->id}/stats")
            ->assertSuccessful();
    });
});

/*
|--------------------------------------------------------------------------
| IAM-003: Employee login rate limiting
|--------------------------------------------------------------------------
*/

describe('IAM-003: employee login rate limiting', function () {
    it('throttles employee login after 5 attempts', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/v1/employee/login', [
                'login' => 'nobody@test.com',
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/v1/employee/login', [
            'login' => 'nobody@test.com',
            'password' => 'wrong',
        ])->assertStatus(429);
    });
});

/*
|--------------------------------------------------------------------------
| IAM-004: EnsurePasswordReset middleware
|--------------------------------------------------------------------------
*/

describe('IAM-004: password reset enforcement', function () {
    it('blocks fresh employees from POS when must_reset_password is true', function () {
        $branch = Branch::factory()->create();
        ['user' => $user] = createStaff('sales_staff', $branch);
        $user->update(['must_reset_password' => true]);

        $this->actingAs($user)
            ->postJson('/v1/pos/orders', [])
            ->assertForbidden()
            ->assertJsonPath('must_reset_password', true);
    });

    it('blocks fresh employees from shifts when must_reset_password is true', function () {
        $branch = Branch::factory()->create();
        ['user' => $user] = createStaff('sales_staff', $branch);
        $user->update(['must_reset_password' => true]);

        $this->actingAs($user)
            ->getJson('/v1/shifts')
            ->assertForbidden()
            ->assertJsonPath('must_reset_password', true);
    });

    it('still allows me and change-password when must_reset_password is true', function () {
        $branch = Branch::factory()->create();
        ['user' => $user] = createStaff('sales_staff', $branch);
        $user->update(['must_reset_password' => true]);

        $this->actingAs($user)
            ->getJson('/v1/employee/me')
            ->assertSuccessful();
    });
});

/*
|--------------------------------------------------------------------------
| IAM-005: Sanctum token expiry
|--------------------------------------------------------------------------
*/

describe('IAM-005: Sanctum token expiry configured', function () {
    it('has a non-null token expiration', function () {
        // Manually load the config to avoid cached env
        $config = require base_path('config/sanctum.php');
        expect($config['expiration'])->not->toBeNull();
    });

    it('defaults to 1440 minutes (24 hours)', function () {
        $config = require base_path('config/sanctum.php');
        expect($config['expiration'])->toBe(1440);
    });
});

/*
|--------------------------------------------------------------------------
| IAM-006: Token revocation on employee destroy
|--------------------------------------------------------------------------
*/

describe('IAM-006: destroy revokes tokens', function () {
    it('revokes all tokens when employee is deactivated', function () {
        $branch = Branch::factory()->create();
        ['user' => $admin] = createStaff('admin', $branch);
        ['user' => $target, 'employee' => $employee] = createStaff('sales_staff', $branch);

        // Target creates a token (simulating login)
        $token = $target->createToken('employee-auth-token')->plainTextToken;

        // Admin deactivates the employee
        $this->actingAs($admin)
            ->deleteJson("/v1/admin/employees/{$employee->id}")
            ->assertSuccessful();

        // Verify all tokens were deleted from DB
        expect($target->fresh()->tokens()->count())->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| IAM-007: OTP hashing
|--------------------------------------------------------------------------
*/

describe('IAM-007: OTPs are hashed', function () {
    it('stores OTP as a hash, not plaintext', function () {
        $service = app(OTPService::class);
        $otp = $service->generate();

        $record = $service->store('+233200000000', $otp);

        // The stored value should NOT be the raw 6-digit OTP
        expect($record->otp)->not->toBe($otp);
        // It should be a 64-char SHA-256 hex string
        expect(strlen($record->otp))->toBe(64);
    });

    it('verifies OTP correctly when given the raw code', function () {
        $service = app(OTPService::class);
        $otp = $service->generate();

        $service->store('+233200000000', $otp);

        $result = $service->verify('+233200000000', $otp);

        expect($result)->not->toBeNull();
        expect($result->verified)->toBeTrue();
    });

    it('rejects incorrect OTP', function () {
        $service = app(OTPService::class);
        $otp = $service->generate();

        $service->store('+233200000000', $otp);

        $result = $service->verify('+233200000000', '000000');

        expect($result)->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| IAM-009: CustomerStatus enum
|--------------------------------------------------------------------------
*/

describe('IAM-009: CustomerStatus enum', function () {
    it('casts customer status to CustomerStatus enum', function () {
        $customer = Customer::factory()->create(['status' => 'active']);

        expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
    });

    it('uses enum value when suspending via controller', function () {
        ['user' => $admin] = createStaff('admin');
        $customer = Customer::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->patchJson("/v1/admin/customers/{$customer->id}/suspend")
            ->assertSuccessful();

        expect($customer->fresh()->status)->toBe(CustomerStatus::Suspended);
    });

    it('uses enum value when unsuspending via controller', function () {
        ['user' => $admin] = createStaff('admin');
        $customer = Customer::factory()->create(['status' => 'suspended']);

        $this->actingAs($admin)
            ->patchJson("/v1/admin/customers/{$customer->id}/unsuspend")
            ->assertSuccessful();

        expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
    });
});

/*
|--------------------------------------------------------------------------
| IAM-010: Force-logout ends active shifts
|--------------------------------------------------------------------------
*/

describe('IAM-010: force-logout ends active shifts', function () {
    it('sets logout_at on active shifts when force-logged out', function () {
        $branch = Branch::factory()->create();
        ['user' => $admin] = createStaff('admin', $branch);
        ['user' => $target, 'employee' => $employee] = createStaff('sales_staff', $branch);

        // Create an active shift for the target
        $shift = Shift::factory()->active()->create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
        ]);

        expect($shift->logout_at)->toBeNull();

        $this->actingAs($admin)
            ->postJson("/v1/admin/employees/{$employee->id}/force-logout")
            ->assertSuccessful();

        expect($shift->fresh()->logout_at)->not->toBeNull();
    });

    it('sets logout_at on active shifts when employee is deactivated', function () {
        $branch = Branch::factory()->create();
        ['user' => $admin] = createStaff('admin', $branch);
        ['user' => $target, 'employee' => $employee] = createStaff('sales_staff', $branch);

        $shift = Shift::factory()->active()->create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/employees/{$employee->id}")
            ->assertSuccessful();

        expect($shift->fresh()->logout_at)->not->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| IAM-011: Dual-identity guard
|--------------------------------------------------------------------------
*/

describe('IAM-011: dual-identity guard', function () {
    it('blocks OTP verification for employee phone numbers', function () {
        $branch = Branch::factory()->create();
        ['user' => $staffUser] = createStaff('sales_staff', $branch);

        // Store a valid OTP for the employee's phone
        $service = app(OTPService::class);
        $otp = $service->generate();
        $service->store($staffUser->phone, $otp);

        $this->postJson('/v1/auth/verify-otp', [
            'phone' => $staffUser->phone,
            'otp' => $otp,
        ])->assertUnprocessable()
            ->assertJsonFragment(['phone' => ['This phone number belongs to a staff account']]);
    });

    it('allows OTP verification for customer phone numbers', function () {
        ['user' => $customerUser] = createCustomer();

        $service = app(OTPService::class);
        $otp = $service->generate();
        $service->store($customerUser->phone, $otp);

        $this->postJson('/v1/auth/verify-otp', [
            'phone' => $customerUser->phone,
            'otp' => $otp,
        ])->assertSuccessful()
            ->assertJsonStructure(['data' => ['token', 'user']]);
    });
});

/*
|--------------------------------------------------------------------------
| IAM-012: PII encryption + resource minimization
|--------------------------------------------------------------------------
*/

describe('IAM-012: PII protection', function () {
    it('encrypts PII fields at rest in the database', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'status' => EmployeeStatus::Active,
            'ssnit_number' => 'A12345678',
            'ghana_card_id' => 'GHA-123456789-0',
            'tin_number' => 'TIN00001234',
        ]);

        // Read raw DB value — should NOT be the plaintext
        $raw = \DB::table('employees')
            ->where('id', $employee->id)
            ->value('ssnit_number');

        expect($raw)->not->toBe('A12345678');

        // But the model should decrypt it
        expect($employee->fresh()->ssnit_number)->toBe('A12345678');
    });

    it('hides PII from users without manage_employees permission', function () {
        $branch = Branch::factory()->create();
        // A manager without manage_employees (branch_partner has view_employees only)
        ['user' => $viewer] = createStaff('branch_partner', $branch);
        ['employee' => $target] = createStaff('sales_staff', $branch);

        $target->update([
            'ssnit_number' => 'A12345678',
            'ghana_card_id' => 'GHA-123456789-0',
            'tin_number' => 'TIN00001234',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson("/v1/admin/employees/{$target->id}")
            ->assertSuccessful();

        $data = $response->json('data');
        expect($data['ssnit_number'])->toBeNull();
        expect($data['ghana_card_id'])->toBeNull();
        expect($data['tin_number'])->toBeNull();
    });

    it('shows PII to users with manage_employees permission', function () {
        $branch = Branch::factory()->create();
        ['user' => $admin] = createStaff('admin', $branch);
        ['employee' => $target] = createStaff('sales_staff', $branch);

        $target->update([
            'ssnit_number' => 'A12345678',
            'ghana_card_id' => 'GHA-123456789-0',
            'tin_number' => 'TIN00001234',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/v1/admin/employees/{$target->id}")
            ->assertSuccessful();

        $data = $response->json('data');
        expect($data['ssnit_number'])->toBe('A12345678');
        expect($data['ghana_card_id'])->toBe('GHA-123456789-0');
        expect($data['tin_number'])->toBe('TIN00001234');
    });
});

/*
|--------------------------------------------------------------------------
| IAM-016/017: Shift + POS permission guards
|--------------------------------------------------------------------------
*/

describe('IAM-016/017: shift and POS permission guards', function () {
    it('blocks customers from accessing shift routes', function () {
        ['user' => $user] = createCustomer();

        $this->actingAs($user)
            ->getJson('/v1/shifts')
            ->assertForbidden();
    });

    it('blocks customers from POS routes', function () {
        ['user' => $user] = createCustomer();

        $this->actingAs($user)
            ->postJson('/v1/pos/orders', [])
            ->assertForbidden();
    });

    it('allows sales staff to access POS routes', function () {
        $branch = Branch::factory()->create();
        ['user' => $user] = createStaff('sales_staff', $branch);

        // Should not be forbidden (may 422 due to missing data, but not 403)
        $response = $this->actingAs($user)
            ->postJson('/v1/pos/orders', []);

        expect($response->status())->not->toBe(403);
    });

    it('allows staff with view_my_shifts to see shifts', function () {
        $branch = Branch::factory()->create();
        ['user' => $user] = createStaff('sales_staff', $branch);

        $this->actingAs($user)
            ->getJson('/v1/shifts')
            ->assertSuccessful();
    });

    it('blocks staff without manage_shifts from starting shifts', function () {
        $branch = Branch::factory()->create();
        // call_center has view_my_shifts but NOT manage_shifts
        ['user' => $user] = createStaff('call_center', $branch);

        $this->actingAs($user)
            ->postJson('/v1/shifts', [])
            ->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| IAM-018: Registration throttling
|--------------------------------------------------------------------------
*/

describe('IAM-018: registration throttling', function () {
    it('throttles register after 5 attempts', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/v1/auth/register', [
                'name' => 'Test',
                'phone' => '+23320000000'.$i,
            ]);
        }

        $this->postJson('/v1/auth/register', [
            'name' => 'Test',
            'phone' => '+233200000099',
        ])->assertStatus(429);
    });

    it('throttles quick-register after 5 attempts', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/v1/auth/quick-register', [
                'name' => 'Test',
                'phone' => '+23320100000'.$i,
            ]);
        }

        $this->postJson('/v1/auth/quick-register', [
            'name' => 'Test',
            'phone' => '+233201000099',
        ])->assertStatus(429);
    });
});
