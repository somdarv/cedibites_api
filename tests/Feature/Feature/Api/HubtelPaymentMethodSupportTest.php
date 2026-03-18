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

    // Create test order for all tests
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $this->order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_number' => 'CB-METHOD-TEST',
        'total_amount' => 100.00,
    ]);

    // Mock Hubtel initiation API
    Http::fake([
        'https://payproxyapi.hubtel.com/items/initiate' => Http::response([
            'status' => 'Success',
            'message' => 'Payment initiated successfully',
            'data' => [
                'checkoutId' => 'method-test-checkout',
                'checkoutUrl' => 'https://checkout.hubtel.com/method-test-checkout',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/method-test-checkout',
                'clientReference' => 'CB-METHOD-TEST',
            ],
        ], 200),
    ]);

    // Initiate payment
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$this->order->id}/payments/hubtel/initiate", [
            'description' => 'Payment method test',
        ]);
});

test('callback with mobile money MTN payment type is stored correctly', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-MTN-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244567890',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();
    expect($payment->payment_method)->toBe('mobile_money');
    expect($payment->payment_status)->toBe('completed');

    // Verify PaymentDetails stored correctly
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['PaymentType'])->toBe('mobilemoney');
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['Channel'])->toBe('mtn-gh');
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['MobileMoneyNumber'])->toBe('233244567890');
});

test('callback with mobile money Vodafone payment type is stored correctly', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-VODA-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233501234567',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233501234567',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'vodafone-gh',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();
    expect($payment->payment_method)->toBe('mobile_money');
    expect($payment->payment_status)->toBe('completed');

    // Verify PaymentDetails stored correctly
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['PaymentType'])->toBe('mobilemoney');
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['Channel'])->toBe('vodafone-gh');
});

test('callback with mobile money AirtelTigo payment type is stored correctly', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-AIRTEL-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233271234567',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233271234567',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'airtel-tigo-gh',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();
    expect($payment->payment_method)->toBe('mobile_money');

    // Verify PaymentDetails stored correctly
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['Channel'])->toBe('airtel-tigo-gh');
});

test('callback with Visa card payment type is stored correctly', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-VISA-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => null,
                'PaymentType' => 'card',
                'Channel' => 'visa',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();
    expect($payment->payment_method)->toBe('card');
    expect($payment->payment_status)->toBe('completed');

    // Verify PaymentDetails stored correctly
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['PaymentType'])->toBe('card');
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['Channel'])->toBe('visa');
});

test('callback with Mastercard payment type is stored correctly', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-MASTER-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => null,
                'PaymentType' => 'card',
                'Channel' => 'mastercard',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();
    expect($payment->payment_method)->toBe('card');

    // Verify PaymentDetails stored correctly
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['Channel'])->toBe('mastercard');
});

test('callback with wallet payment type is stored correctly', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-WALLET-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => null,
                'PaymentType' => 'wallet',
                'Channel' => 'hubtel-wallet',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();
    expect($payment->payment_method)->toBe('wallet');
    expect($payment->payment_status)->toBe('completed');

    // Verify PaymentDetails stored correctly
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['PaymentType'])->toBe('wallet');
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['Channel'])->toBe('hubtel-wallet');
});

test('callback with GhQR payment type is stored correctly', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-GHQR-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => null,
                'PaymentType' => 'ghqr',
                'Channel' => 'ghqr',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();
    expect($payment->payment_method)->toBe('ghqr');
    expect($payment->payment_status)->toBe('completed');

    // Verify PaymentDetails stored correctly
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['PaymentType'])->toBe('ghqr');
});

test('callback with cash payment type is stored correctly', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-CASH-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => null,
                'PaymentType' => 'cash',
                'Channel' => 'cash',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();
    expect($payment->payment_method)->toBe('cash');
    expect($payment->payment_status)->toBe('completed');

    // Verify PaymentDetails stored correctly
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['PaymentType'])->toBe('cash');
});

test('all payment details are preserved in payment_gateway_response', function () {
    // Arrange
    $callbackPayload = [
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Data' => [
            'CheckoutId' => 'method-test-checkout',
            'SalesInvoiceId' => 'INV-COMPLETE-001',
            'ClientReference' => 'CB-METHOD-TEST',
            'Status' => 'Paid',
            'Amount' => 100.00,
            'CustomerPhoneNumber' => '233244567890',
            'PaymentDetails' => [
                'MobileMoneyNumber' => '233244567890',
                'PaymentType' => 'mobilemoney',
                'Channel' => 'mtn-gh',
                'AdditionalField1' => 'value1',
                'AdditionalField2' => 'value2',
            ],
        ],
    ];

    // Act
    $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

    // Assert
    $response->assertOk();

    $payment = Payment::where('order_id', $this->order->id)->first();

    // Verify complete callback payload is stored
    expect($payment->payment_gateway_response)->toBeArray();
    expect($payment->payment_gateway_response['ResponseCode'])->toBe('0000');
    expect($payment->payment_gateway_response['Status'])->toBe('Success');
    expect($payment->payment_gateway_response['Data'])->toBeArray();
    expect($payment->payment_gateway_response['Data']['CheckoutId'])->toBe('method-test-checkout');
    expect($payment->payment_gateway_response['Data']['SalesInvoiceId'])->toBe('INV-COMPLETE-001');
    expect($payment->payment_gateway_response['Data']['Amount'])->toBe(100);
    expect($payment->payment_gateway_response['Data']['PaymentDetails'])->toBeArray();
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['AdditionalField1'])->toBe('value1');
    expect($payment->payment_gateway_response['Data']['PaymentDetails']['AdditionalField2'])->toBe('value2');
});
