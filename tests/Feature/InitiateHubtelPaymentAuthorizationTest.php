<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Set test Hubtel configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'merchant_account_number' => 'test_merchant_account',
        'base_url' => 'https://payproxyapi.hubtel.com',
        'status_check_url' => 'https://api-txnstatus.hubtel.com',
    ]);

    // Mock Hubtel API responses for all tests
    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'test-checkout-id',
                'checkoutUrl' => 'https://checkout.hubtel.com/test',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
                'clientReference' => 'ORD-123456',
            ],
        ], 200),
    ]);
});

test('authenticated user can initiate payment for their own order', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #'.$order->order_number,
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'payment_status' => 'pending',
    ]);
});

test('authenticated user cannot initiate payment for another users order', function () {
    $user1 = User::factory()->create();
    $customer1 = Customer::factory()->create(['user_id' => $user1->id]);

    $user2 = User::factory()->create();
    $customer2 = Customer::factory()->create(['user_id' => $user2->id]);

    $order = Order::factory()->create(['customer_id' => $customer2->id]);

    $response = $this->actingAs($user1, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #'.$order->order_number,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('payments', [
        'order_id' => $order->id,
        'customer_id' => $customer1->id,
    ]);
});

test('guest user can initiate payment for order without customer_id', function () {
    $order = Order::factory()->create(['customer_id' => null]);

    $response = $this->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
        'description' => 'Payment for Order #'.$order->order_number,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'customer_id' => null,
        'payment_status' => 'pending',
    ]);
});

test('authorization fails for order with completed payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    // Create a completed payment for this order
    Payment::factory()->completed()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #'.$order->order_number,
        ]);

    // The authorization should fail (either 403 Forbidden or 409 Conflict is acceptable)
    expect($response->status())->toBeIn([403, 409]);
});

test('authorization passes for order with pending payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    // Create a pending payment for this order (should allow retry)
    Payment::factory()->pending()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #'.$order->order_number,
        ]);

    $response->assertOk();
});

test('authorization passes for order with failed payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    // Create a failed payment for this order (should allow retry)
    Payment::factory()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'payment_status' => 'failed',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #'.$order->order_number,
        ]);

    $response->assertOk();
});
