<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\BranchManagerAssignedNotification;
use App\Notifications\BranchManagerRemovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchManagerNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles with api guard
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        Role::create(['name' => 'manager', 'guard_name' => 'api']);
        Role::create(['name' => 'employee', 'guard_name' => 'api']);

        // Create permissions
        \Spatie\Permission\Models\Permission::create(['name' => 'manage_branches', 'guard_name' => 'api']);
    }

    public function test_notification_sent_when_manager_assigned_to_branch(): void
    {
        Notification::fake();

        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('manage_branches');

        // Create employee to be assigned as manager
        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee'); // Initially employee

        // Create branch
        $branch = Branch::factory()->create();

        // Update branch with new manager
        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/branches/{$branch->id}", [
                'manager_id' => $employee->id,
            ]);

        $response->assertOk();

        // Assert notification was sent to the new manager
        Notification::assertSentTo(
            $employee->user,
            BranchManagerAssignedNotification::class,
            function ($notification) use ($branch) {
                return $notification->branch->id === $branch->id;
            }
        );
    }

    public function test_notification_sent_when_manager_removed_from_branch(): void
    {
        Notification::fake();

        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('manage_branches');

        // Create employee who is currently a manager
        $currentManager = Employee::factory()->create();
        $currentManager->user->assignRole('manager');

        // Create branch and assign current manager
        $branch = Branch::factory()->create();
        $branch->employees()->attach($currentManager->id);

        // Remove manager by setting manager_id to null
        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/branches/{$branch->id}", [
                'manager_id' => null,
            ]);

        $response->assertOk();

        // Assert notification was sent to the removed manager
        Notification::assertSentTo(
            $currentManager->user,
            BranchManagerRemovedNotification::class,
            function ($notification) use ($branch) {
                return $notification->branch->id === $branch->id;
            }
        );
    }

    public function test_notifications_sent_when_manager_replaced(): void
    {
        Notification::fake();

        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('manage_branches');

        // Create current manager
        $currentManager = Employee::factory()->create();
        $currentManager->user->assignRole('manager');

        // Create new manager
        $newManager = Employee::factory()->create();
        $newManager->user->assignRole('employee');

        // Create branch and assign current manager
        $branch = Branch::factory()->create();
        $branch->employees()->attach($currentManager->id);

        // Replace manager
        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/branches/{$branch->id}", [
                'manager_id' => $newManager->id,
            ]);

        $response->assertOk();

        // Assert removal notification was sent to old manager
        Notification::assertSentTo(
            $currentManager->user,
            BranchManagerRemovedNotification::class,
            function ($notification) use ($branch) {
                return $notification->branch->id === $branch->id;
            }
        );

        // Assert assignment notification was sent to new manager
        Notification::assertSentTo(
            $newManager->user,
            BranchManagerAssignedNotification::class,
            function ($notification) use ($branch) {
                return $notification->branch->id === $branch->id;
            }
        );
    }

    public function test_notification_contains_correct_branch_information(): void
    {
        Notification::fake();

        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('manage_branches');

        // Create employee
        $employee = Employee::factory()->create();

        // Create branch with specific details
        $branch = Branch::factory()->create([
            'name' => 'Test Branch',
            'address' => '123 Test Street',
        ]);

        // Assign manager
        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/branches/{$branch->id}", [
                'manager_id' => $employee->id,
            ]);

        $response->assertOk();

        // Assert notification contains correct branch data
        Notification::assertSentTo(
            $employee->user,
            BranchManagerAssignedNotification::class,
            function ($notification) use ($branch, $employee) {
                $data = $notification->toArray($employee->user);

                return $data['branch_id'] === $branch->id &&
                       $data['branch_name'] === 'Test Branch' &&
                       str_contains($data['message'], 'Test Branch');
            }
        );
    }
}
