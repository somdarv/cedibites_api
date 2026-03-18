<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.hubtel.client_id' => 'test_client_id',
        'services.hubtel.client_secret' => 'test_client_secret',
        'services.hubtel.merchant_account_number' => 'test_merchant_account',
        'services.hubtel.base_url' => 'https://payproxyapi.hubtel.com',
        'services.hubtel.status_check_url' => 'https://api-txnstatus.hubtel.com',
    ]);
});

test('authenticated user can manually verify payment status', function () {
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
        'payment_status' => 'pending',
        'amount' => 100.00,
    ]);

    // Mock Hubtel Status Check API response
    Http::fake([
        '*' => Http::response([
            'transactionId' => 'test-txn-id-123',
            'externalTransactionId' => 'EXT-456789',
            'amount' => 100.00,
            'charges' => 2.50,
            'status' => 'Paid',
            'clientReference' => 'CB123456',
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user)
        ->getJson("/api/v1/payments/{$payment->id}/verify");

    // Assert
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'id',
            'order_id',
            'payment_method',
            'payment_status',
            'amount',
            'transaction_id',
            'paid_at',
        ],
    ]);

    // Verify payment was updated to completed
    $payment->refresh();
    expect($payment->payment_status)->toBe('completed');
    expect($payment->paid_at)->not->toBeNull();
})->group('feature', 'hubtel', 'verification');

test('payment verification updates status when changed', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB789012',
        'total_amount' => 150.00,
    ]);

    $payment = Payment::factory()->pending()->create([
        'order_id' => $order->id,
        'amount' => 150.00,
    ]);

    // Mock Hubtel Status Check API response with Unpaid status
    Http::fake([
        '*' => Http::response([
            'transactionId' => 'test-txn-id-456',
            'externalTransactionId' => 'EXT-123456',
            'amount' => 150.00,
            'charges' => 3.00,
            'status' => 'Unpaid',
            'clientReference' => 'CB789012',
        ], 200),
    ]);

    // Act
    $response = $this->actingAs($user)
        ->getJson("/api/v1/payments/{$payment->id}/verify");

    // Assert
    $response->assertOk();

    // Verify payment status remains pending for Unpaid status
    $payment->refresh();
    expect($payment->payment_status)->toBe('pending');
    expect($payment->paid_at)->toBeNull();
})->group('feature', 'hubtel', 'verification');

test('payment verification requires authentication', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_number' => 'CB999999',
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'payment_status' => 'pending',
    ]);

    // Act - Try to verify without authentication
    $response = $this->getJson("/api/v1/payments/{$payment->id}/verify");

    // Assert
    $response->assertUnauthorized();
})->group('feature', 'hubtel', 'verification');
