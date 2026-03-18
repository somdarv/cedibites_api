<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as PermissionModel;
use Tests\TestCase;

class AnalyticsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        PermissionModel::create(['name' => Permission::ViewOrders->value, 'guard_name' => 'api']);

        // Create admin user
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
        ]);

        // Give the user analytics permissions
        $user->givePermissionTo(Permission::ViewOrders->value);

        $this->actingAs($user);
    }

    public function test_can_access_sales_analytics(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/sales');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_sales',
                    'total_orders',
                    'average_order_value',
                    'sales_by_day',
                    'sales_by_type',
                ],
            ]);
    }

    public function test_can_access_order_source_analytics(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/order-sources');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }

    public function test_can_access_top_items_analytics(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/top-items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }

    public function test_can_access_category_revenue_analytics(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/category-revenue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }

    public function test_can_access_branch_performance_analytics(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/branch-performance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }

    public function test_can_access_delivery_pickup_analytics(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/delivery-pickup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'delivery_pct',
                    'pickup_pct',
                ],
            ]);
    }

    public function test_can_access_payment_methods_analytics(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }
}
