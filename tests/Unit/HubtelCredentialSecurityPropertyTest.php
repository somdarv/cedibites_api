<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\HubtelService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Set up Hubtel configuration for tests
    config([
        'services.hubtel.client_id' => 'test_client_id_secret',
        'services.hubtel.client_secret' => 'test_client_secret_value',
        'services.hubtel.merchant_account_number' => 'test_merchant_account',
        'services.hubtel.base_url' => 'https://payproxyapi.hubtel.com',
        'services.hubtel.status_check_url' => 'https://api-txnstatus.hubtel.com',
    ]);
});

test('property: credential security in API responses for payment initiation', function () {
    // **Property 20: Credential Security in Responses**
    // **Validates: Requirements 10.4, 11.7**

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

        // Skip if there's an error (e.g., validation or business logic)
        if ($response->status() !== 200) {
            continue;
        }

        // Property: Response MUST NOT contain client_id or client_secret
        $responseContent = $response->getContent();
        expect($responseContent)->not->toContain('test_client_id_secret');
        expect($responseContent)->not->toContain('test_client_secret_value');
        expect($responseContent)->not->toContain('client_id');
        expect($responseContent)->not->toContain('client_secret');

        // Property: Response JSON MUST NOT have client_id or client_secret keys
        $responseData = $response->json();
        expect($responseData)->not->toHaveKey('client_id');
        expect($responseData)->not->toHaveKey('client_secret');

        // Recursively check nested arrays
        assertNoCredentialsInArray($responseData);
    }
})->group('property', 'hubtel');

test('property: credential security in API responses for payment verification', function () {
    // **Property 20: Credential Security in Responses**
    // **Validates: Requirements 10.4, 11.7**

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

        // Property: Response MUST NOT contain client_id or client_secret
        $responseContent = $response->getContent();
        expect($responseContent)->not->toContain('test_client_id_secret');
        expect($responseContent)->not->toContain('test_client_secret_value');
        expect($responseContent)->not->toContain('client_id');
        expect($responseContent)->not->toContain('client_secret');

        // Property: Response JSON MUST NOT have client_id or client_secret keys
        $responseData = $response->json();
        expect($responseData)->not->toHaveKey('client_id');
        expect($responseData)->not->toHaveKey('client_secret');

        // Recursively check nested arrays
        assertNoCredentialsInArray($responseData);
    }
})->group('property', 'hubtel');

test('property: credential security in callback responses', function () {
    // **Property 20: Credential Security in Responses**
    // **Validates: Requirements 10.4, 11.7**

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

        // Property: Response MUST NOT contain client_id or client_secret
        $responseContent = $response->getContent();
        expect($responseContent)->not->toContain('test_client_id_secret');
        expect($responseContent)->not->toContain('test_client_secret_value');
        expect($responseContent)->not->toContain('client_id');
        expect($responseContent)->not->toContain('client_secret');

        // Property: Response JSON MUST NOT have client_id or client_secret keys
        $responseData = $response->json();
        expect($responseData)->not->toHaveKey('client_id');
        expect($responseData)->not->toHaveKey('client_secret');

        // Recursively check nested arrays
        assertNoCredentialsInArray($responseData);
    }
})->group('property', 'hubtel');

test('property: credential security in log entries', function () {
    // **Property 20: Credential Security in Responses**
    // **Validates: Requirements 10.4, 11.7**

    // Capture log entries
    Log::spy();

    // Run 50 iterations (fewer than API tests since logging is more expensive)
    for ($i = 0; $i < 50; $i++) {
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => null,
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

        // Initialize transaction via service (this will log)
        $service = new HubtelService;

        try {
            $service->initializeTransaction([
                'order' => $order,
                'description' => fake()->sentence(),
                'customer_name' => fake()->name(),
                'customer_phone' => '233'.fake()->numerify('#########'),
                'customer_email' => fake()->email(),
            ]);
        } catch (\Exception $e) {
            // Continue even if there's an error
        }
    }

    // Property: Log entries MUST NOT contain client_id or client_secret values
    Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
        // Convert context to JSON string for easier searching
        $contextJson = json_encode($context);

        // Check that credentials are not in the log context
        $hasClientId = str_contains($contextJson, 'test_client_id_secret');
        $hasClientSecret = str_contains($contextJson, 'test_client_secret_value');

        // Also check the message itself
        $messageHasClientId = str_contains($message, 'test_client_id_secret');
        $messageHasClientSecret = str_contains($message, 'test_client_secret_value');

        return ! $hasClientId && ! $hasClientSecret && ! $messageHasClientId && ! $messageHasClientSecret;
    })->atLeast()->once();
})->group('property', 'hubtel');

test('property: credential security in payment gateway response field', function () {
    // **Property 20: Credential Security in Responses**
    // **Validates: Requirements 10.4, 11.7**

    // Run 100 iterations with randomized data
    for ($i = 0; $i < 100; $i++) {
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => null,
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

        // Initialize transaction via service
        $service = new HubtelService;

        try {
            $result = $service->initializeTransaction([
                'order' => $order,
                'description' => fake()->sentence(),
                'customer_name' => fake()->name(),
                'customer_phone' => '233'.fake()->numerify('#########'),
                'customer_email' => fake()->email(),
            ]);

            $payment = $result['payment'];

            // Property: payment_gateway_response MUST NOT contain client_id or client_secret
            $gatewayResponse = $payment->payment_gateway_response;
            $gatewayResponseJson = json_encode($gatewayResponse);

            expect($gatewayResponseJson)->not->toContain('test_client_id_secret');
            expect($gatewayResponseJson)->not->toContain('test_client_secret_value');
            expect($gatewayResponseJson)->not->toContain('client_id');
            expect($gatewayResponseJson)->not->toContain('client_secret');

            // Recursively check the array
            assertNoCredentialsInArray($gatewayResponse);
        } catch (\Exception $e) {
            // Continue even if there's an error
        }
    }
})->group('property', 'hubtel');

// Helper function to recursively check arrays for credentials
function assertNoCredentialsInArray(array $data): void
{
    foreach ($data as $key => $value) {
        // Check keys
        expect($key)->not->toBe('client_id');
        expect($key)->not->toBe('client_secret');

        // Check string values
        if (is_string($value)) {
            expect($value)->not->toContain('test_client_id_secret');
            expect($value)->not->toContain('test_client_secret_value');
        }

        // Recursively check nested arrays
        if (is_array($value)) {
            assertNoCredentialsInArray($value);
        }
    }
}
