<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Set up Hubtel configuration for tests
    config([
        'services.hubtel.client_id' => 'test_client_id',
        'services.hubtel.client_secret' => 'test_client_secret',
        'services.hubtel.merchant_account_number' => 'test_merchant_account',
        'services.hubtel.base_url' => 'https://payproxyapi.hubtel.com',
        'services.hubtel.status_check_url' => 'https://api-txnstatus.hubtel.com',
    ]);
});

test('authenticated customer can initiate payment', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB123456',
        'total_amount' => 100.00,
        'contact_name' => 'John Doe',
        'contact_phone' => '233244123456',
    ]);

    // Mock Hubtel API response - fake all HTTP requests
    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'test-checkout-id-123',
                'checkoutUrl' => 'https://checkout.hubtel.com/test-checkout-id-123',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test-checkout-id-123',
                'clientReference' => 'CB123456',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'customer_name' => 'John Doe',
            'customer_phone' => '233244123456',
            'customer_email' => 'john@example.com',
            'description' => 'Payment for Order #CB123456',
        ]);

    // Assert
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_id',
                'payment_method',
                'payment_status',
                'amount',
                'transaction_id',
                'checkout_url',
                'checkout_direct_url',
                'paid_at',
                'created_at',
                'updated_at',
            ],
        ]);

    expect($response->json('data.order_id'))->toBe($order->id);
    expect($response->json('data.payment_status'))->toBe('pending');
    expect($response->json('data.amount'))->toBe('100.00');
    expect($response->json('data.transaction_id'))->toBe('test-checkout-id-123');
    expect($response->json('data.checkout_url'))->toBe('https://checkout.hubtel.com/test-checkout-id-123');
    expect($response->json('data.checkout_direct_url'))->toBe('https://checkout.hubtel.com/direct/test-checkout-id-123');

    // Verify Payment record created in database
    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->customer_id)->toBe($customer->id);
    expect($payment->payment_status)->toBe('pending');
    expect($payment->amount)->toBe('100.00');
    expect($payment->transaction_id)->toBe('test-checkout-id-123');

    // Verify payment_gateway_response contains complete Hubtel response
    expect($payment->payment_gateway_response)->toBeArray();
    expect($payment->payment_gateway_response['checkoutId'])->toBe('test-checkout-id-123');
    expect($payment->payment_gateway_response['checkoutUrl'])->toBe('https://checkout.hubtel.com/test-checkout-id-123');
    expect($payment->payment_gateway_response['checkoutDirectUrl'])->toBe('https://checkout.hubtel.com/direct/test-checkout-id-123');
    expect($payment->payment_gateway_response['clientReference'])->toBe('CB123456');
});

test('guest customer can initiate payment', function () {
    // Arrange
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => null, // Guest order
        'branch_id' => $branch->id,
        'order_number' => 'CB789012',
        'total_amount' => 150.00,
        'contact_name' => 'Jane Smith',
        'contact_phone' => '233201987654',
    ]);

    // Mock Hubtel API response - fake all HTTP requests
    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'guest-checkout-id-456',
                'checkoutUrl' => 'https://checkout.hubtel.com/guest-checkout-id-456',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/guest-checkout-id-456',
                'clientReference' => 'CB789012',
            ],
        ], 200),
    ]);

    // Act - No authentication for guest customer
    $response = $this->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
        'description' => 'Payment for Order #CB789012',
    ]);

    // Assert
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_id',
                'payment_method',
                'payment_status',
                'amount',
                'transaction_id',
                'checkout_url',
                'checkout_direct_url',
            ],
        ]);

    expect($response->json('data.order_id'))->toBe($order->id);
    expect($response->json('data.payment_status'))->toBe('pending');
    expect($response->json('data.amount'))->toBe('150.00');
    expect($response->json('data.transaction_id'))->toBe('guest-checkout-id-456');

    // Verify Payment record created with customer_id as null
    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->customer_id)->toBeNull(); // Guest customer
    expect($payment->payment_status)->toBe('pending');
    expect($payment->amount)->toBe('150.00');
    expect($payment->transaction_id)->toBe('guest-checkout-id-456');

    // Verify payment_gateway_response contains complete Hubtel response
    expect($payment->payment_gateway_response)->toBeArray();
    expect($payment->payment_gateway_response['checkoutId'])->toBe('guest-checkout-id-456');
    expect($payment->payment_gateway_response['clientReference'])->toBe('CB789012');
});

test('guest customer payment uses order contact info for Hubtel payer details', function () {
    // Arrange
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => null, // Guest order
        'branch_id' => $branch->id,
        'order_number' => 'CB555666',
        'total_amount' => 200.00,
        'contact_name' => 'Guest User',
        'contact_phone' => '233501234567',
    ]);

    // Mock Hubtel API and capture the request
    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'guest-checkout-789',
                'checkoutUrl' => 'https://checkout.hubtel.com/guest-checkout-789',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/guest-checkout-789',
                'clientReference' => 'CB555666',
            ],
        ], 200),
    ]);

    // Act - No authentication, no customer details provided
    $response = $this->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
        'description' => 'Payment for Order #CB555666',
    ]);

    // Assert response is successful
    $response->assertOk();

    // Verify the request sent to Hubtel included order contact info
    Http::assertSent(function ($request) use ($order) {
        $body = $request->data();

        return $body['payeeName'] === $order->contact_name
            && $body['payeeMobileNumber'] === $order->contact_phone
            && $body['totalAmount'] === $order->total_amount
            && $body['clientReference'] === $order->order_number;
    });

    // Verify Payment record created with customer_id as null
    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->customer_id)->toBeNull();
});

test('payment initiation fails when order already has completed payment', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB111222',
        'total_amount' => 100.00,
    ]);

    // Create a completed payment for the order
    Payment::factory()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'payment_status' => 'completed',
        'amount' => 100.00,
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #CB111222',
        ]);

    // Assert
    $response->assertStatus(409)
        ->assertJson([
            'message' => 'Order has already been paid',
        ]);
});

test('authenticated user cannot initiate payment for another users order', function () {
    // Arrange
    $user1 = User::factory()->create();
    $customer1 = Customer::factory()->create(['user_id' => $user1->id]);

    $user2 = User::factory()->create();
    $customer2 = Customer::factory()->create(['user_id' => $user2->id]);

    $branch = Branch::factory()->create();

    // Order belongs to customer2
    $order = Order::factory()->create([
        'customer_id' => $customer2->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB333444',
        'total_amount' => 100.00,
    ]);

    // Act - user1 tries to pay for user2's order
    $response = $this->actingAs($user1, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #CB333444',
        ]);

    // Assert
    $response->assertStatus(403)
        ->assertJson([
            'message' => 'Unauthorized',
        ]);
});

test('payment initiation validates required fields', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
    ]);

    // Act - Missing required description field
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", []);

    // Assert - Validation errors return 422
    $response->assertStatus(422);
});

test('payment initiation validates phone number format', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
    ]);

    // Act - Invalid phone format
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Test payment',
            'customer_phone' => '0244123456', // Invalid format (should start with 233)
        ]);

    // Assert - Validation errors return 422
    $response->assertStatus(422);
});

test('payment initiation validates email format', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
    ]);

    // Act - Invalid email format
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Test payment',
            'customer_email' => 'invalid-email',
        ]);

    // Assert - Validation errors return 422
    $response->assertStatus(422);
});
