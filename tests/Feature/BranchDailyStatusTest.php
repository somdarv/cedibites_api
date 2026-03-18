<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchDailyStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Permission::create(['name' => 'manage_branches', 'guard_name' => 'api']);
    }

    public function test_admin_can_toggle_branch_daily_status(): void
    {
        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('manage_branches');

        // Create branch (factory will create operating hours automatically)
        $branch = Branch::factory()->create();
        $today = strtolower(now()->format('l'));

        // Get the operating hour that was created by the factory
        $operatingHour = $branch->operatingHours()->where('day_of_week', $today)->first();
        $this->assertNotNull($operatingHour);
        $this->assertTrue($operatingHour->is_open); // Should be open by default
        $this->assertNull($operatingHour->manual_override_open); // No manual override initially

        // Test toggling to closed
        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/branches/{$branch->id}/toggle-status");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'message' => 'Branch manually closed successfully',
                    'is_open' => false,
                    'is_manual_override' => true,
                    'day' => $today,
                ],
            ]);

        // Verify the database was updated
        $operatingHour->refresh();
        $this->assertFalse($operatingHour->manual_override_open);
        $this->assertNotNull($operatingHour->manual_override_at);

        // Test toggling back to open
        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/branches/{$branch->id}/toggle-status");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'message' => 'Branch manually opened successfully',
                    'is_open' => true,
                    'is_manual_override' => true,
                    'day' => $today,
                ],
            ]);

        // Verify the database was updated
        $operatingHour->refresh();
        $this->assertTrue($operatingHour->manual_override_open);
        $this->assertNotNull($operatingHour->manual_override_at);
    }

    public function test_returns_error_when_no_operating_hours_exist(): void
    {
        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('manage_branches');

        // Create branch and delete today's operating hours
        $branch = Branch::factory()->create();
        $today = strtolower(now()->format('l'));

        // Delete today's operating hours to simulate the error condition
        $branch->operatingHours()->where('day_of_week', $today)->delete();

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/branches/{$branch->id}/toggle-status");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'No operating hours found for today',
            ]);
    }

    public function test_unauthorized_user_cannot_toggle_status(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/admin/branches/{$branch->id}/toggle-status");

        $response->assertForbidden();
    }

    public function test_manual_override_logic(): void
    {
        $branch = Branch::factory()->create();
        $today = strtolower(now()->format('l'));
        $operatingHour = $branch->operatingHours()->where('day_of_week', $today)->first();

        // Initially should follow schedule (open during business hours)
        $this->assertTrue($operatingHour->isCurrentlyOpen());

        // Set manual override to closed
        $operatingHour->update([
            'manual_override_open' => false,
            'manual_override_at' => now(),
        ]);

        // Should now be closed despite being in business hours
        $this->assertFalse($operatingHour->fresh()->isCurrentlyOpen());

        // Set manual override to open
        $operatingHour->update([
            'manual_override_open' => true,
            'manual_override_at' => now(),
        ]);

        // Should now be open (admin decision overrides schedule completely)
        $this->assertTrue($operatingHour->fresh()->isCurrentlyOpen());

        // Manual overrides persist - no automatic reset
        // This is the key difference: admin decisions stay until changed
        $this->assertTrue($operatingHour->fresh()->isCurrentlyOpen());
    }
}
