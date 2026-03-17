<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\PasswordResetRequiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake(); // Fake all notifications to prevent SMS issues

    $this->seed();

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    // Create test employee
    $this->branch = Branch::first();
    $this->testUser = User::factory()->create();
    $this->testUser->assignRole('call_center');
    $this->employee = Employee::factory()->create([
        'user_id' => $this->testUser->id,
    ]);
    $this->employee->branches()->attach($this->branch->id);
});

describe('Employee Force Logout', function () {
    test('admin can force logout employee', function () {
        // Create some tokens for the employee
        $this->testUser->createToken('test-token-1');
        $this->testUser->createToken('test-token-2');

        expect($this->testUser->tokens()->count())->toBe(2);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/employees/{$this->employee->id}/force-logout");

        $response->assertOk()
            ->assertJson([
                'message' => 'Employee logged out successfully.',
            ]);

        // Verify all tokens were deleted
        expect($this->testUser->fresh()->tokens()->count())->toBe(0);
    });

    test('non-admin cannot force logout employee', function () {
        $regularUser = User::factory()->create();
        $regularUser->assignRole('call_center');

        $response = $this->actingAs($regularUser, 'sanctum')
            ->postJson("/api/v1/admin/employees/{$this->employee->id}/force-logout");

        $response->assertForbidden();
    });
});

describe('Employee Password Reset Requirement', function () {
    test('admin can require password reset for employee', function () {
        expect($this->testUser->must_reset_password)->toBeFalse();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/employees/{$this->employee->id}/require-password-reset");

        $response->assertOk()
            ->assertJson([
                'message' => 'Password reset required successfully.',
            ]);

        // Verify database was updated
        $this->testUser->refresh();
        expect($this->testUser->must_reset_password)->toBeTrue();
        expect($this->testUser->password_reset_required_at)->not->toBeNull();

        // Verify notification was sent
        Notification::assertSentTo(
            $this->testUser,
            PasswordResetRequiredNotification::class
        );
    });

    test('non-admin cannot require password reset for employee', function () {
        $regularUser = User::factory()->create();
        $regularUser->assignRole('call_center');

        $response = $this->actingAs($regularUser, 'sanctum')
            ->postJson("/api/v1/admin/employees/{$this->employee->id}/require-password-reset");

        $response->assertForbidden();
    });
});
