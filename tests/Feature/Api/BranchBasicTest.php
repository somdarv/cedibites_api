<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchBasicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(['PermissionSeeder', 'RoleSeeder', 'BranchSeeder']);
    }

    public function test_can_get_basic_branches(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/branches/basic');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'area',
                    'address',
                    'is_active',
                ],
            ],
        ]);

        $branches = $response->json('data');
        $this->assertGreaterThan(0, count($branches));

        // Check that we have expected branches
        $branchNames = collect($branches)->pluck('name')->toArray();
        $this->assertContains('Accra Central', $branchNames);
        $this->assertContains('East Legon', $branchNames);

        // Ensure no menu items are included
        foreach ($branches as $branch) {
            $this->assertArrayNotHasKey('menu_items', $branch);
            $this->assertArrayNotHasKey('menuItems', $branch);
        }
    }

    public function test_requires_view_branches_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('kitchen'); // This role doesn't have view_branches permission

        $response = $this->actingAs($user)->getJson('/api/v1/admin/branches/basic');
        $response->assertStatus(403);
    }
}
