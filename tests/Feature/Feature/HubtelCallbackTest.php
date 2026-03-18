<?php

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Set test Hubtel configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'merchant_account_number' => 'test_merchant_account',
        'base_url' => 'https://payproxyapi.hubtel.com',
        'status_check_url' => 'https://api-txnstatus.hubtel.com',
    ]);
});

test('callback with status Refunded updates payment correctly', function () {
    // Create order and payment
    $order = Order::factory()->create();
    $payment = Payment::factory()->completed()->create([
        'order_id' => $order->id,
        'payment_status' => 'completed',
        'paid_at' => now()->subDays(5),
        'refunded_at' => null,
        'refund_reason' => null,
    ]);

    // Generate refund callback payload
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Refunded',
        'Data' => [
            'CheckoutId' => 'test-checkout-id',
            'SalesInvoiceId' => 'INV-123456',
            'ClientReference' => $order->order_number,
            'Status' => 'Refunded',
            'Amount' => (float) $payment->amount,
            'CustomerPhoneNumber' => '233123456789',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233123456789',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    // Send callback request
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Verify response
    $response->assertOk();

    // Refresh payment from database
    $payment->refresh();

    // Verify payment status updated to refunded
    expect($payment->payment_status)->toBe('refunded');

    // Verify refunded_at timestamp is set
    expect($payment->refunded_at)->not->toBeNull();
    expect($payment->refunded_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);

    // Verify complete callback stored in payment_gateway_response
    expect($payment->payment_gateway_response)->toBeArray();
    expect($payment->payment_gateway_response['Status'])->toBe('Refunded');
    expect($payment->payment_gateway_response['ResponseCode'])->toBe('0000');
});

test('callback with status Refunded and refund reason stores reason', function () {
    // Create order and payment
    $order = Order::factory()->create();
    $payment = Payment::factory()->completed()->create([
        'order_id' => $order->id,
        'payment_status' => 'completed',
        'paid_at' => now()->subDays(5),
        'refunded_at' => null,
        'refund_reason' => null,
    ]);

    $refundReason = 'Customer requested refund due to order cancellation';

    // Generate refund callback payload with refund reason
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Refunded',
        'Data' => [
            'CheckoutId' => 'test-checkout-id',
            'SalesInvoiceId' => 'INV-123456',
            'ClientReference' => $order->order_number,
            'Status' => 'Refunded',
            'Amount' => (float) $payment->amount,
            'CustomerPhoneNumber' => '233123456789',
            'RefundReason' => $refundReason,
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233123456789',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    // Send callback request
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Verify response
    $response->assertOk();

    // Refresh payment from database
    $payment->refresh();

    // Verify payment status updated to refunded
    expect($payment->payment_status)->toBe('refunded');

    // Verify refunded_at timestamp is set
    expect($payment->refunded_at)->not->toBeNull();

    // Verify refund_reason is stored
    expect($payment->refund_reason)->not->toBeNull();
    expect($payment->refund_reason)->toBe($refundReason);
});

test('callback with ResponseCode 0000 and Status Success updates payment to completed', function () {
    // Create order and payment
    $order = Order::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'order_id' => $order->id,
        'payment_status' => 'pending',
        'paid_at' => null,
    ]);

    // Generate success callback payload
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'test-checkout-id',
            'SalesInvoiceId' => 'INV-123456',
            'ClientReference' => $order->order_number,
            'Status' => 'Paid',
            'Amount' => (float) $payment->amount,
            'CustomerPhoneNumber' => '233123456789',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233123456789',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    // Send callback request
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Verify response
    $response->assertOk();

    // Refresh payment from database
    $payment->refresh();

    // Verify payment status updated to completed
    expect($payment->payment_status)->toBe('completed');

    // Verify paid_at timestamp is set
    expect($payment->paid_at)->not->toBeNull();
    expect($payment->paid_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('callback with ResponseCode 2001 updates payment to failed', function () {
    // Create order and payment
    $order = Order::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'order_id' => $order->id,
        'payment_status' => 'pending',
        'paid_at' => null,
    ]);

    // Generate failed callback payload
    $callbackPayload = [
        'ResponseCode' => '2001',
        'Status' => 'Failed',
        'Data' => [
            'CheckoutId' => 'test-checkout-id',
            'SalesInvoiceId' => 'INV-123456',
            'ClientReference' => $order->order_number,
            'Status' => 'Failed',
            'Amount' => (float) $payment->amount,
            'CustomerPhoneNumber' => '233123456789',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233123456789',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    // Send callback request
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Verify response
    $response->assertOk();

    // Refresh payment from database
    $payment->refresh();

    // Verify payment status updated to failed
    expect($payment->payment_status)->toBe('failed');

    // Verify paid_at remains null
    expect($payment->paid_at)->toBeNull();
});
