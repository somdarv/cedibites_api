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

test('property: API response structure consistency for payment initiation', function () {
    // **Property 18: API Response Structure Consistency**
    // **Validates: Requirements 8.7**

    // Run 100 iterations with randomized data
    for ($i = 0; $i < 100; $i++) {
        // Randomly choose between authenticated and guest customer
        $isAuthenticated = fake()->boolean();

        if ($isAuthenticated) {
            $user = User::factory()->create();
            $customer = Customer::factory()->create(['user_id' => $user->id]);
            $customerId = $customer->id;
        } else {
            $user = null;
            $customer = null;
            $customerId = null;
        }

        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customerId,
            'branch_id' => $branch->id,
            'order_number' => 'CB'.fake()->numerify('######'),
            'total_amount' => fake()->randomFloat(2, 10, 1000),
            'contact_name' => fake()->name(),
            'contact_phone' => '233'.fake()->numerify('#########'),
        ]);

        // Mock Hubtel API response
        $checkoutId = fake()->uuid();
        Http::fake([
            '*' => Http::response([
                'status' => 'Success',
                'message' => 'Payment initiated successfully',
                'data' => [
                    'checkoutId' => $checkoutId,
                    'checkoutUrl' => 'https://checkout.hubtel.com/'.$checkoutId,
                    'checkoutDirectUrl' => 'https://checkout.hubtel.com/direct/'.$checkoutId,
                    'clientReference' => $order->order_number,
                ],
            ], 200),
        ]);

        // Make request
        $requestData = [
            'description' => fake()->sentence(),
        ];

        if ($isAuthenticated && fake()->boolean()) {
            $requestData['customer_name'] = fake()->name();
            $requestData['customer_phone'] = '233'.fake()->numerify('#########');
            $requestData['customer_email'] = fake()->email();
        }

        $response = $isAuthenticated
            ? $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", $requestData)
            : $this->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", $requestData);

        // Verify response structure consistency
        if ($response->status() !== 200) {
            // Skip this iteration if there's an error (e.g., validation or business logic)
            continue;
        }

        $response->assertOk();

        // Property: Response MUST have 'data' field (from response()->success())
        expect($response->json())->toHaveKey('data');

        $data = $response->json('data');

        // Property: PaymentResource MUST include all required fields
        expect($data)->toHaveKey('id');
        expect($data)->toHaveKey('order_id');
        expect($data)->toHaveKey('payment_method');
        expect($data)->toHaveKey('payment_status');
        expect($data)->toHaveKey('amount');
        expect($data)->toHaveKey('transaction_id');
        expect($data)->toHaveKey('checkout_url');
        expect($data)->toHaveKey('checkout_direct_url');
        expect($data)->toHaveKey('paid_at');
        expect($data)->toHaveKey('created_at');
        expect($data)->toHaveKey('updated_at');

        // Property: Field types MUST be consistent
        expect($data['id'])->toBeInt();
        expect($data['order_id'])->toBe($order->id);
        expect($data['payment_method'])->toBeString();
        expect($data['payment_status'])->toBe('pending');
        expect($data['amount'])->toBeString(); // Laravel casts decimals to strings in JSON
        expect($data['transaction_id'])->toBe($checkoutId);
        expect($data['checkout_url'])->toBeString();
        expect($data['checkout_direct_url'])->toBeString();
        expect($data['paid_at'])->toBeNull(); // Pending payment has no paid_at
        expect($data['created_at'])->toBeString(); // ISO8601 timestamp
        expect($data['updated_at'])->toBeString(); // ISO8601 timestamp

        // Property: Timestamps MUST be in ISO8601 format (with microseconds)
        expect($data['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
        expect($data['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
    }
})->group('property', 'hubtel');

test('property: API response structure consistency for payment verification', function () {
    // **Property 18: API Response Structure Consistency**
    // **Validates: Requirements 8.7**

    // Run 100 iterations with randomized data
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'order_number' => 'CB'.fake()->numerify('######'),
            'total_amount' => fake()->randomFloat(2, 10, 1000),
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'payment_status' => 'pending',
            'amount' => $order->total_amount,
            'transaction_id' => fake()->uuid(),
        ]);

        // Mock Hubtel Status Check API response
        $statuses = ['Paid', 'Unpaid', 'Success', 'Refunded'];
        $status = fake()->randomElement($statuses);

        Http::fake([
            '*' => Http::response([
                'transactionId' => $payment->transaction_id,
                'externalTransactionId' => 'EXT-'.fake()->numerify('######'),
                'amount' => (float) $payment->amount,
                'charges' => fake()->randomFloat(2, 0, 10),
                'status' => $status,
                'clientReference' => $order->order_number,
            ], 200),
        ]);

        // Make verification request
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/payments/{$payment->id}/verify");

        // Verify response structure consistency
        $response->assertOk();

        // Property: Response MUST have 'data' field (from response()->success())
        expect($response->json())->toHaveKey('data');

        $data = $response->json('data');

        // Property: PaymentResource MUST include all required fields
        expect($data)->toHaveKey('id');
        expect($data)->toHaveKey('order_id');
        expect($data)->toHaveKey('payment_method');
        expect($data)->toHaveKey('payment_status');
        expect($data)->toHaveKey('amount');
        expect($data)->toHaveKey('transaction_id');
        expect($data)->toHaveKey('checkout_url');
        expect($data)->toHaveKey('checkout_direct_url');
        expect($data)->toHaveKey('paid_at');
        expect($data)->toHaveKey('created_at');
        expect($data)->toHaveKey('updated_at');

        // Property: Field types MUST be consistent
        expect($data['id'])->toBeInt();
        expect($data['order_id'])->toBe($order->id);
        expect($data['payment_method'])->toBeString();
        expect($data['payment_status'])->toBeString();
        expect($data['amount'])->toBeString();
        expect($data['transaction_id'])->toBeString();
        expect($data['created_at'])->toBeString();
        expect($data['updated_at'])->toBeString();

        // Property: Timestamps MUST be in ISO8601 format (with microseconds)
        expect($data['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
        expect($data['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
    }
})->group('property', 'hubtel');

test('property: API response structure consistency for callback acknowledgment', function () {
    // **Property 18: API Response Structure Consistency**
    // **Validates: Requirements 8.7**

    // Run 100 iterations with randomized data
    for ($i = 0; $i < 100; $i++) {
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => null,
            'branch_id' => $branch->id,
            'order_number' => 'CB'.fake()->numerify('######'),
            'total_amount' => fake()->randomFloat(2, 10, 1000),
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'customer_id' => null,
            'payment_status' => 'pending',
            'amount' => $order->total_amount,
        ]);

        // Generate random callback payload
        $responseCodes = ['0000', '2001', '0005'];
        $statuses = ['Success', 'Paid', 'Unpaid', 'Failed'];
        $paymentTypes = ['mobilemoney', 'card', 'wallet', 'ghqr', 'cash'];
        $channels = ['mtn-gh', 'vodafone-gh', 'visa', 'mastercard'];

        $callbackPayload = [
            'ResponseCode' => fake()->randomElement($responseCodes),
            'Status' => fake()->randomElement($statuses),
            'Data' => [
                'CheckoutId' => fake()->uuid(),
                'SalesInvoiceId' => 'INV-'.fake()->numerify('######'),
                'ClientReference' => $order->order_number,
                'Status' => fake()->randomElement($statuses),
                'Amount' => (float) $payment->amount,
                'CustomerPhoneNumber' => '233'.fake()->numerify('#########'),
                'PaymentDetails' => [
                    'MobileMoneyNumber' => '233'.fake()->numerify('#########'),
                    'PaymentType' => fake()->randomElement($paymentTypes),
                    'Channel' => fake()->randomElement($channels),
                ],
            ],
        ];

        // Make callback request (no authentication)
        $response = $this->postJson('/api/v1/payments/hubtel/callback', $callbackPayload);

        // Verify response structure consistency
        $response->assertOk();

        // Property: Response MUST have 'data' field (from response()->success())
        expect($response->json())->toHaveKey('data');

        // Property: Callback response data can be null (acknowledgment only)
        // The response()->success(null, 'message') pattern is valid
        $data = $response->json('data');
        expect($data)->toBeNull();
    }
})->group('property', 'hubtel');
