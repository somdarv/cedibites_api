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

test('complete payment flow for authenticated customer: initiate → callback → verify', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-E2E-001',
        'total_amount' => 250.00,
        'contact_name' => 'Alice Johnson',
        'contact_phone' => '233244567890',
    ]);

    // Mock Hubtel API responses
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'e2e-checkout-001',
                'checkoutUrl' => 'https://checkout.hubtel.com/e2e-checkout-001',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/e2e-checkout-001',
                'clientReference' => 'CB-E2E-001',
            ],
        ], 200),
        'https://api-txnstatus.hubtel.com/transactions/*/status*' => Http::response([
            'transactionId' => 'e2e-checkout-001',
            'externalTransactionId' => 'EXT-E2E-001',
            'amount' => 250.00,
            'charges' => 5.00,
            'status' => 'Paid',
            'clientReference' => 'CB-E2E-001',
        ], 200),
    ]);

    // Step 1: Initiate Payment
    $initiateResponse = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'customer_name' => 'Alice Johnson',
            'customer_phone' => '233244567890',
            'customer_email' => 'alice@example.com',
            'description' => 'Payment for Order #CB-E2E-001',
        ]);

    $initiateResponse->assertOk();
    expect($initiateResponse->json('data.payment_status'))->toBe('pending');
    expect($initiateResponse->json('data.transaction_id'))->toBe('e2e-checkout-001');

    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->payment_status)->toBe('pending');
    expect($payment->paid_at)->toBeNull();

    // Step 2: Receive Callback from Hubtel
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'e2e-checkout-001',
            'SalesInvoiceId' => 'INV-E2E-001',
            'ClientReference' => 'CB-E2E-001',
            'Status' => 'Paid',
            'Amount' => 250.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244567890',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    $callbackResponse = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    $callbackResponse->assertOk();

    // Verify payment updated to completed
    $payment->refresh();
    expect($payment->payment_status)->toBe('completed');
    expect($payment->payment_method)->toBe('mobile_money');
    expect($payment->paid_at)->not->toBeNull();

    // Verify callback data stored in payment_gateway_response
    expect($payment->payment_gateway_response)->toBeArray();
    expect($payment->payment_gateway_response['ResponseCode'])->toBe('0000');
    expect($payment->payment_gateway_response['Data']['CheckoutId'])->toBe('e2e-checkout-001');
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['Channel'])->toBe('mtn-gh');

    // Step 3: Manual Verification
    $verifyResponse = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/payments/{$payment->id}/verify");

    $verifyResponse->assertOk();
    expect($verifyResponse->json('data.payment_status'))->toBe('completed');
    expect($verifyResponse->json('data.transaction_id'))->toBe('e2e-checkout-001');
});

test('complete payment flow for guest customer: initiate → callback → verify', function () {
    // Arrange
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => null, // Guest order
        'branch_id' => $branch->id,
        'order_number' => 'CB-GUEST-E2E-002',
        'total_amount' => 180.00,
        'contact_name' => 'Guest User',
        'contact_phone' => '233201234567',
    ]);

    // Mock Hubtel API responses
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'guest-e2e-002',
                'checkoutUrl' => 'https://checkout.hubtel.com/guest-e2e-002',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/guest-e2e-002',
                'clientReference' => 'CB-GUEST-E2E-002',
            ],
        ], 200),
        'https://api-txnstatus.hubtel.com/transactions/*/status*' => Http::response([
            'transactionId' => 'guest-e2e-002',
            'externalTransactionId' => 'EXT-GUEST-002',
            'amount' => 180.00,
            'charges' => 3.60,
            'status' => 'Paid',
            'clientReference' => 'CB-GUEST-E2E-002',
        ], 200),
    ]);

    // Step 1: Initiate Payment (no authentication)
    $initiateResponse = $this->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
        'description' => 'Payment for Order #CB-GUEST-E2E-002',
    ]);

    $initiateResponse->assertOk();
    expect($initiateResponse->json('data.payment_status'))->toBe('pending');

    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->customer_id)->toBeNull(); // Guest customer
    expect($payment->payment_status)->toBe('pending');

    // Verify order contact info was used for Hubtel payer details
    Http::assertSent(function ($request) use ($order) {
        $body = $request->data();

        return $body['payeeName'] === $order->contact_name
            && $body['payeeMobileNumber'] === $order->contact_phone;
    });

    // Step 2: Receive Callback from Hubtel
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'guest-e2e-002',
            'SalesInvoiceId' => 'INV-GUEST-002',
            'ClientReference' => 'CB-GUEST-E2E-002',
            'Status' => 'Paid',
            'Amount' => 180.00,
            'CustomerPhoneNumber' => '233201234567',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233201234567',
                'PaymentType' => 'card',
                'Channel' => 'visa',
            ],
        ],
    ];

    $callbackResponse = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    $callbackResponse->assertOk();

    // Verify payment updated to completed
    $payment->refresh();
    expect($payment->payment_status)->toBe('completed');
    expect($payment->payment_method)->toBe('card');
    expect($payment->paid_at)->not->toBeNull();
    expect($payment->customer_id)->toBeNull(); // Still null for guest

    // Step 3: Manual Verification (create temporary user for auth)
    $adminUser = User::factory()->create();
    $verifyResponse = $this->actingAs($adminUser, 'sanctum')
        ->getJson("/api/v1/payments/{$payment->id}/verify");

    $verifyResponse->assertOk();
    expect($verifyResponse->json('data.payment_status'))->toBe('completed');
});

test('payment flow handles failed payment callback correctly', function () {
    // Arrange
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-FAIL-003',
        'total_amount' => 120.00,
    ]);

    // Mock Hubtel API
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'fail-checkout-003',
                'checkoutUrl' => 'https://checkout.hubtel.com/fail-checkout-003',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/fail-checkout-003',
                'clientReference' => 'CB-FAIL-003',
            ],
        ], 200),
    ]);

    // Step 1: Initiate Payment
    $initiateResponse = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", [
            'description' => 'Payment for Order #CB-FAIL-003',
        ]);

    $initiateResponse->assertOk();

    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment->payment_status)->toBe('pending');

    // Step 2: Receive Failed Callback from Hubtel
    $callbackPayload = [
        'ResponseCode' => '2001',
        'Status' => 'Failed',
        'Data' => [
            'CheckoutId' => 'fail-checkout-003',
            'SalesInvoiceId' => 'INV-FAIL-003',
            'ClientReference' => 'CB-FAIL-003',
            'Status' => 'Failed',
            'Amount' => 120.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244567890',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'vodafone-gh',
            ],
        ],
    ];

    $callbackResponse = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    $callbackResponse->assertOk();

    // Verify payment updated to failed
    $payment->refresh();
    expect($payment->payment_status)->toBe('failed');
    expect($payment->paid_at)->toBeNull(); // Should remain null for failed payment
});
