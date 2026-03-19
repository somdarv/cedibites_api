<?php

use App\Models\Customer;
use App\Models\Order;
use App\Services\HubtelPaymentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.hubtel.client_id' => 'test_client_id',
        'services.hubtel.client_secret' => 'test_client_secret',
        'services.hubtel.merchant_account_number' => 'HM12345',
        'app.frontend_url' => 'https://example.com',
    ]);

    // Mock the route helper since routes aren't loaded in unit tests
    if (! function_exists('route')) {
        function route($name)
        {
            return 'https://api.example.com/payments/hubtel/callback';
        }
    }
});

test('property: payment initiation request completeness', function () {
    // **Property 1: Payment Initiation Request Completeness**
    // **Validates: Requirements 1.2, 3.4, 3.5**

    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-123456',
        'total_amount' => 100.00,
    ]);

    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'data' => [
                'checkoutId' => 'test-checkout-id',
                'checkoutUrl' => 'https://checkout.hubtel.com/test',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
                'clientReference' => $order->order_number,
            ],
        ]),
    ]);

    $service = new HubtelPaymentService;

    $result = $service->initializeTransaction([
        'order' => $order,
        'description' => 'Test payment',
        'customer_name' => 'John Doe',
        'customer_phone' => '233123456789',
        'customer_email' => 'john@example.com',
    ]);

    // Verify HTTP request was made with all required fields
    Http::assertSent(function ($request) use ($order) {
        $data = $request->data();

        return $request->url() === 'https://payproxyapi.hubtel.com/items/initiate'
            && isset($data['totalAmount'])
            && isset($data['description'])
            && isset($data['callbackUrl'])
            && isset($data['returnUrl'])
            && isset($data['cancellationUrl'])
            && isset($data['merchantAccountNumber'])
            && isset($data['clientReference'])
            && $data['clientReference'] === $order->order_number;
    });

    expect($result)->toHaveKeys(['payment', 'checkoutUrl', 'checkoutDirectUrl', 'checkoutId', 'clientReference']);
});

test('property: client reference derivation', function () {
    // **Property 2: Client Reference Derivation**
    // **Validates: Requirements 1.3, 12.4**

    $order = Order::factory()->create([
        'order_number' => 'ORD-123456789012345678901234567890XX', // 35 chars, exceeds 32
        'total_amount' => 100.00,
    ]);

    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'data' => [
                'checkoutId' => 'test-checkout-id',
                'checkoutUrl' => 'https://checkout.hubtel.com/test',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
                'clientReference' => substr($order->order_number, 0, 32),
            ],
        ]),
    ]);

    $service = new HubtelPaymentService;

    $result = $service->initializeTransaction([
        'order' => $order,
        'description' => 'Test payment',
    ]);

    // Verify clientReference is derived from order_number and doesn't exceed 32 chars
    Http::assertSent(function ($request) use ($order) {
        $data = $request->data();
        $clientRef = $data['clientReference'] ?? '';

        return strlen($clientRef) <= 32
            && str_starts_with($order->order_number, $clientRef);
    });

    expect($result['clientReference'])->not->toBeNull();
    expect(strlen($result['clientReference']))->toBeLessThanOrEqual(32);
});

test('property: optional customer details inclusion', function () {
    // **Property 3: Optional Customer Details Inclusion**
    // **Validates: Requirements 1.4**

    $order = Order::factory()->create([
        'order_number' => 'ORD-123456',
        'total_amount' => 100.00,
    ]);

    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'data' => [
                'checkoutId' => 'test-checkout-id',
                'checkoutUrl' => 'https://checkout.hubtel.com/test',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
                'clientReference' => $order->order_number,
            ],
        ]),
    ]);

    $service = new HubtelPaymentService;

    // Test with customer details provided
    $service->initializeTransaction([
        'order' => $order,
        'description' => 'Test payment',
        'customer_name' => 'Jane Doe',
        'customer_phone' => '233987654321',
        'customer_email' => 'jane@example.com',
    ]);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['payeeName']) && $data['payeeName'] === 'Jane Doe'
            && isset($data['payeeMobileNumber']) && $data['payeeMobileNumber'] === '233987654321'
            && isset($data['payeeEmail']) && $data['payeeEmail'] === 'jane@example.com';
    });
});

test('property: checkout URLs response structure', function () {
    // **Property 4: Checkout URLs Response Structure**
    // **Validates: Requirements 1.5, 3.1, 3.2, 3.3, 7.4**

    $order = Order::factory()->create([
        'order_number' => 'ORD-123456',
        'total_amount' => 100.00,
    ]);

    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'data' => [
                'checkoutId' => 'test-checkout-id',
                'checkoutUrl' => 'https://checkout.hubtel.com/test',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
                'clientReference' => $order->order_number,
            ],
        ]),
    ]);

    $service = new HubtelPaymentService;

    $result = $service->initializeTransaction([
        'order' => $order,
        'description' => 'Test payment',
    ]);

    // Verify response structure includes all required fields
    expect($result)->toHaveKeys(['checkoutUrl', 'checkoutDirectUrl', 'checkoutId', 'clientReference']);
    expect($result['checkoutUrl'])->toBeString();
    expect($result['checkoutDirectUrl'])->toBeString();
    expect($result['checkoutId'])->toBeString();
    expect($result['clientReference'])->toBe($order->order_number);
});

test('property: payment record creation', function () {
    // **Property 5: Payment Record Creation**
    // **Validates: Requirements 1.6, 6.3, 6.4**

    $order = Order::factory()->create([
        'order_number' => 'ORD-123456',
        'total_amount' => 150.50,
    ]);

    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'data' => [
                'checkoutId' => 'test-checkout-id-xyz',
                'checkoutUrl' => 'https://checkout.hubtel.com/test',
                'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
                'clientReference' => $order->order_number,
            ],
        ]),
    ]);

    $service = new HubtelPaymentService;

    $result = $service->initializeTransaction([
        'order' => $order,
        'description' => 'Test payment',
    ]);

    // Verify Payment record was created with correct attributes
    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'payment_status' => 'pending',
        'amount' => '150.50',
        'transaction_id' => 'test-checkout-id-xyz',
    ]);

    expect($result['payment'])->not->toBeNull();
    expect($result['payment']->order_id)->toBe($order->id);
    expect($result['payment']->payment_status)->toBe('pending');
    expect($result['payment']->transaction_id)->toBe('test-checkout-id-xyz');
});

test('property: gateway response persistence', function () {
    // **Property 6: Gateway Response Persistence**
    // **Validates: Requirements 1.7, 4.5, 5.7, 6.5, 9.7**

    $order = Order::factory()->create([
        'order_number' => 'ORD-123456',
        'total_amount' => 100.00,
    ]);

    $hubtelResponse = [
        'checkoutId' => 'test-checkout-id',
        'checkoutUrl' => 'https://checkout.hubtel.com/test',
        'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/test',
        'clientReference' => $order->order_number,
    ];

    Http::fake([
        '*' => Http::response([
            'status' => 'Success',
            'data' => $hubtelResponse,
        ]),
    ]);

    $service = new HubtelPaymentService;

    $result = $service->initializeTransaction([
        'order' => $order,
        'description' => 'Test payment',
    ]);

    // Verify complete Hubtel response is stored in payment_gateway_response
    expect($result['payment']->payment_gateway_response)->toBeArray();
    expect($result['payment']->payment_gateway_response)->toBe($hubtelResponse);

    // Verify it's persisted in database
    $payment = $result['payment']->fresh();
    expect($payment->payment_gateway_response)->toBe($hubtelResponse);
});
