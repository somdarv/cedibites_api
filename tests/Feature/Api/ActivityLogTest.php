<?php

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
});

test('order creation logs activity', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create();
    $user = $customer->user;

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'status' => 'received',
    ]);

    $activity = Activity::inLog('orders')
        ->where('subject_type', Order::class)
        ->where('subject_id', $order->id)
        ->where('description', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('attributes'))->toHaveKey('order_number');
    expect($activity->properties->get('attributes')['status'])->toBe('received');
});

test('order status change by employee logs activity with causer', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'status' => 'received',
    ]);

    $employeeUser = User::factory()->create();
    $employeeUser->assignRole(Role::Employee->value);
    \App\Models\Employee::factory()->forBranches([$branch])->create([
        'user_id' => $employeeUser->id,
        'status' => EmployeeStatus::Active,
    ]);

    $response = $this->actingAs($employeeUser, 'sanctum')
        ->patchJson("/api/v1/employee/orders/{$order->id}/status", [
            'status' => 'preparing',
            'notes' => 'Started cooking',
        ]);

    $response->assertOk();

    $activity = Activity::inLog('orders')
        ->where('event', 'status_changed')
        ->where('subject_id', $order->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toContain('preparing');
    expect($activity->causer_id)->toBe($employeeUser->id);
    expect($activity->properties->get('old_status'))->toBe('received');
    expect($activity->properties->get('new_status'))->toBe('preparing');
    expect($activity->properties->get('notes'))->toBe('Started cooking');
});

test('payment refund logs activity with causer', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
    ]);

    $payment = Payment::factory()->completed()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'amount' => 100.00,
    ]);

    $adminUser = User::factory()->create();
    $adminUser->assignRole(Role::Admin->value);

    $response = $this->actingAs($adminUser, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund");

    $response->assertOk();

    $activity = Activity::inLog('payments')
        ->where('event', 'refunded')
        ->where('subject_id', $payment->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($adminUser->id);
    expect($activity->properties->get('order_number'))->toBe($order->order_number);
    expect((float) $activity->properties->get('amount'))->toBe(100.0);
});

test('guest cart claim logs activity', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();
    $category = MenuCategory::factory()->create();
    $menuItem = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $category->id,
    ]);
    $menuItemSize = MenuItemSize::factory()->create([
        'menu_item_id' => $menuItem->id,
    ]);

    $guestSessionId = 'guest-'.fake()->unique()->numerify('##########').'-'.fake()->lexify('??????');

    $guestCart = Cart::factory()->guest()->create([
        'session_id' => $guestSessionId,
        'branch_id' => $branch->id,
    ]);

    CartItem::factory()->create([
        'cart_id' => $guestCart->id,
        'menu_item_id' => $menuItem->id,
        'menu_item_size_id' => $menuItemSize->id,
        'quantity' => 2,
        'unit_price' => 25.00,
        'subtotal' => 50.00,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/claim-guest', [
            'guest_session_id' => $guestSessionId,
        ]);

    $response->assertOk();

    $activity = Activity::inLog('cart')
        ->where('description', 'Guest cart claimed')
        ->where('causer_id', $user->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('guest_session_id'))->toBe($guestSessionId);
    expect($activity->properties->get('carts_claimed'))->toBe(1);
    expect($activity->properties->get('items_merged'))->toBe(1);
});

test('admin can fetch activity logs via API', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole(Role::Admin->value);

    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create();
    Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'status' => 'received',
    ]);

    $response = $this->actingAs($adminUser, 'sanctum')
        ->getJson('/api/v1/admin/activity-logs?per_page=5');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'log_name',
                'description',
                'event',
                'subject_type',
                'subject_id',
                'causer',
                'properties',
                'created_at',
                'entity',
                'severity',
            ],
        ],
        'meta' => [
            'current_page',
            'per_page',
            'total',
        ],
    ]);
    expect(count($response->json('data')))->toBeGreaterThan(0);
});

test('admin activity logs API supports filters', function () {
    $adminUser = User::factory()->create();
    $adminUser->assignRole(Role::Admin->value);

    $response = $this->actingAs($adminUser, 'sanctum')
        ->getJson('/api/v1/admin/activity-logs?log_name=orders&per_page=10');

    $response->assertOk();
});

test('non-admin cannot fetch activity logs', function () {
    $branch = \App\Models\Branch::factory()->create();
    $employeeUser = User::factory()->create();
    $employeeUser->assignRole(Role::Employee->value);
    \App\Models\Employee::factory()->forBranches([$branch])->create([
        'user_id' => $employeeUser->id,
        'status' => EmployeeStatus::Active,
    ]);

    $response = $this->actingAs($employeeUser, 'sanctum')
        ->getJson('/api/v1/admin/activity-logs');

    $response->assertForbidden();
});
