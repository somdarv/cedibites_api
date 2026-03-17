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
        'app.frontend_url' => 'https://cedibites.com',
    ]);
});

test('payment initiation response includes both checkoutUrl and checkoutDirectUrl', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-CHECKOUT-001',
        'total_amount' => 150.00,
    ]);

    // Mock Hubtel API response with both checkout URLs
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'checkout-test-001',
                'checkoutUrl' => 'https://checkout.hubtel.com/checkout-test-001',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/checkout-test-001',
                'clientReference' => 'CB-CHECKOUT-001',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #CB-CHECKOUT-001',
        ]);

    // Assert - Response includes both URLs
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'checkout_url',
                'checkout_direct_url',
            ],
        ]);

    expect($response->json('data.checkout_url'))->toBe('https://checkout.hubtel.com/checkout-test-001');
    expect($response->json('data.checkout_direct_url'))->toBe('https://checkout.hubtel.com/direct/checkout-test-001');

    // Verify both URLs stored in payment_gateway_response
    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment->payment_gateway_response['checkoutUrl'])->toBe('https://checkout.hubtel.com/checkout-test-001');
    expect($payment->payment_gateway_response['checkoutDirectUrl'])->toBe('https://checkout.hubtel.com/direct/checkout-test-001');
});

test('payment initiation sends correct returnUrl to Hubtel', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-RETURN-002',
        'total_amount' => 200.00,
    ]);

    // Mock Hubtel API
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'return-test-002',
                'checkoutUrl' => 'https://checkout.hubtel.com/return-test-002',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/return-test-002',
                'clientReference' => 'CB-RETURN-002',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #CB-RETURN-002',
        ]);

    // Assert
    $response->assertOk();

    // Verify returnUrl was sent to Hubtel with correct format
    Http::assertSent(function ($request) use ($order) {
        $body = $request->data();

        return isset($body['returnUrl'])
            && str_contains($body['returnUrl'], $order->order_number)
            && str_contains($body['returnUrl'], 'payment/success');
    });
});

test('payment initiation sends correct cancellationUrl to Hubtel', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-CANCEL-003',
        'total_amount' => 175.00,
    ]);

    // Mock Hubtel API
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'cancel-test-003',
                'checkoutUrl' => 'https://checkout.hubtel.com/cancel-test-003',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/cancel-test-003',
                'clientReference' => 'CB-CANCEL-003',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #CB-CANCEL-003',
        ]);

    // Assert
    $response->assertOk();

    // Verify cancellationUrl was sent to Hubtel with correct format
    Http::assertSent(function ($request) use ($order) {
        $body = $request->data();

        return isset($body['cancellationUrl'])
            && str_contains($body['cancellationUrl'], $order->order_number)
            && str_contains($body['cancellationUrl'], 'payment/cancelled');
    });
});

test('payment initiation sends correct callbackUrl to Hubtel', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-CALLBACK-004',
        'total_amount' => 125.00,
    ]);

    // Mock Hubtel API
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'callback-test-004',
                'checkoutUrl' => 'https://checkout.hubtel.com/callback-test-004',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/callback-test-004',
                'clientReference' => 'CB-CALLBACK-004',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #CB-CALLBACK-004',
        ]);

    // Assert
    $response->assertOk();

    // Verify callbackUrl was sent to Hubtel
    Http::assertSent(function ($request) {
        $body = $request->data();

        return isset($body['callbackUrl'])
            && str_contains($body['callbackUrl'], '/api/v1/payments/hubtel/callback');
    });
});

test('payment initiation includes all required fields for Hubtel API', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-COMPLETE-005',
        'total_amount' => 300.00,
    ]);

    // Mock Hubtel API
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'complete-test-005',
                'checkoutUrl' => 'https://checkout.hubtel.com/complete-test-005',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/complete-test-005',
                'clientReference' => 'CB-COMPLETE-005',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'customer_name' => 'Test Customer',
            'customer_phone' => '233244567890',
            'customer_email' => 'test@example.com',
            'description' => 'Payment for Order #CB-COMPLETE-005',
        ]);

    // Assert
    $response->assertOk();

    // Verify all required fields were sent to Hubtel
    Http::assertSent(function ($request) use ($order) {
        $body = $request->data();

        return isset($body['totalAmount'])
            && isset($body['description'])
            && isset($body['callbackUrl'])
            && isset($body['returnUrl'])
            && isset($body['cancellationUrl'])
            && isset($body['merchantAccountNumber'])
            && isset($body['clientReference'])
            && $body['totalAmount'] === $order->total_amount
            && $body['clientReference'] === $order->order_number
            && $body['merchantAccountNumber'] === 'test_merchant_account';
    });
});

test('payment initiation includes optional customer details when provided', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-OPTIONAL-006',
        'total_amount' => 250.00,
    ]);

    // Mock Hubtel API
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'optional-test-006',
                'checkoutUrl' => 'https://checkout.hubtel.com/optional-test-006',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/optional-test-006',
                'clientReference' => 'CB-OPTIONAL-006',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'customer_name' => 'John Doe',
            'customer_phone' => '233244567890',
            'customer_email' => 'john@example.com',
            'description' => 'Payment for Order #CB-OPTIONAL-006',
        ]);

    // Assert
    $response->assertOk();

    // Verify optional customer details were sent to Hubtel
    Http::assertSent(function ($request) {
        $body = $request->data();

        return isset($body['payeeName'])
            && isset($body['payeeMobileNumber'])
            && isset($body['payeeEmail'])
            && $body['payeeName'] === 'John Doe'
            && $body['payeeMobileNumber'] === '233244567890'
            && $body['payeeEmail'] === 'john@example.com';
    });
});

test('checkout URLs support both redirect and onsite integration', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-INTEGRATION-007',
        'total_amount' => 180.00,
    ]);

    // Mock Hubtel API with distinct URLs for redirect and onsite
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'integration-test-007',
                'checkoutUrl' => 'https://checkout.hubtel.com/integration-test-007',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/integration-test-007',
                'clientReference' => 'CB-INTEGRATION-007',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #CB-INTEGRATION-007',
        ]);

    // Assert - Both URLs are different and present
    $response->assertOk();

    $checkoutUrl = $response->json('data.checkout_url');
    $checkoutDirectUrl = $response->json('data.checkout_direct_url');

    expect($checkoutUrl)->not->toBeNull();
    expect($checkoutDirectUrl)->not->toBeNull();
    expect($checkoutUrl)->not->toBe($checkoutDirectUrl);

    // Verify redirect URL doesn't contain 'direct'
    expect($checkoutUrl)->not->toContain('/direct/');

    // Verify onsite URL contains 'direct'
    expect($checkoutDirectUrl)->toContain('/direct/');
});
