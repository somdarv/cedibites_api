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

test('initiate endpoint works with authentication', function () {
    // **Validates: Requirements 8.5, 15.7, 15.8**

    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB'.fake()->numerify('######'),
        'total_amount' => 100.00,
        'contact_name' => 'John Doe',
        'contact_phone' => '233244123456',
    ]);

    // Mock Hubtel API response
    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'data' => [
                'checkoutId' => 'test-checkout-id',
                'checkoutUrl' => 'https://checkout.hubtel.com/test',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
                'clientReference' => $order->order_number,
            ],
        ], 200),
    ]);

    // Act - authenticated request
    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #'.$order->order_number,
        ]);

    // Assert
    $response->assertOk();
    expect($response->json('data.payment_status'))->toBe('pending');
});

test('initiate endpoint works without authentication for guest customers', function () {
    // **Validates: Requirements 8.5, 15.7, 15.8**

    // Arrange
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => null, // Guest order
        'branch_id' => $branch->id,
        'order_number' => 'CB'.fake()->numerify('######'),
        'total_amount' => 100.00,
        'contact_name' => 'Guest Customer',
        'contact_phone' => '233244123456',
    ]);

    // Mock Hubtel API response
    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'data' => [
                'checkoutId' => 'test-checkout-id',
                'checkoutUrl' => 'https://checkout.hubtel.com/test',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
                'clientReference' => $order->order_number,
            ],
        ], 200),
    ]);

    // Act - unauthenticated request (guest)
    $response = $this->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
        'description' => 'Payment for Order #'.$order->order_number,
    ]);

    // Assert
    $response->assertOk();
    expect($response->json('data.payment_status'))->toBe('pending');

    // Verify Payment record created with null customer_id
    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->customer_id)->toBeNull();
});

test('callback endpoint works without authentication', function () {
    // **Validates: Requirements 8.5, 15.7**

    // Arrange
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => null,
        'branch_id' => $branch->id,
        'order_number' => 'CB123456',
        'total_amount' => 100.00,
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'customer_id' => null,
        'payment_method' => 'mobile_money',
        'payment_status' => 'pending',
        'amount' => 100.00,
        'transaction_id' => 'test-checkout-id',
    ]);

    // Act - unauthenticated callback request from Hubtel
    $response = $this->postJson('/api/v1/payments/hubtel/callback', [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'test-checkout-id',
            'SalesInvoiceId' => 'INV-123',
            'ClientReference' => 'CB123456',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244123456',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244123456',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ]);

    // Assert - callback should work without authentication
    $response->assertOk();

    // Verify payment was updated
    $payment->refresh();
    expect($payment->payment_status)->toBe('completed');
    expect($payment->payment_method)->toBe('mobile_money');
});

test('verify endpoint requires authentication', function () {
    // **Validates: Requirements 8.5, 15.6**

    // Arrange
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => null,
        'branch_id' => $branch->id,
        'order_number' => 'CB123456',
        'total_amount' => 100.00,
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'customer_id' => null,
        'payment_method' => 'mobile_money',
        'payment_status' => 'pending',
        'amount' => 100.00,
        'transaction_id' => 'test-checkout-id',
    ]);

    // Act - unauthenticated request
    $response = $this->getJson("/api/v1/payments/{$payment->id}/verify");

    // Assert - should require authentication
    $response->assertUnauthorized();
});

test('verify endpoint works with authentication', function () {
    // **Validates: Requirements 8.5, 15.6**

    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB123456',
        'total_amount' => 100.00,
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'payment_method' => 'mobile_money',
        'payment_status' => 'pending',
        'amount' => 100.00,
        'transaction_id' => 'test-checkout-id',
    ]);

    // Mock Hubtel Status Check API response
    Http::fake([
        '*' => Http::response([
            'transactionId' => 'test-checkout-id',
            'externalTransactionId' => 'EXT-123',
            'amount' => 100.00,
            'charges' => 2.50,
            'status' => 'Paid',
            'clientReference' => 'CB123456',
        ], 200),
    ]);

    // Act - authenticated request
    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/payments/{$payment->id}/verify");

    // Assert - should work with authentication
    $response->assertOk();
    expect($response->json('data.payment_status'))->toBe('completed');
});
