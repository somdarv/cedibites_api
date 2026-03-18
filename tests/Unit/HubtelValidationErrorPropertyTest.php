<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;

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

test('property: validation error response format', function () {
    // **Property 19: Validation Error Response Format**
    // **Validates: Requirements 8.8, 12.8**

    // Run 100 iterations with randomized invalid data
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

        // Ensure order belongs to the customer to avoid authorization errors
        $order = Order::factory()->create([
            'customer_id' => $customerId,
            'branch_id' => $branch->id,
            'order_number' => 'CB'.fake()->numerify('######'),
            'total_amount' => fake()->randomFloat(2, 10, 1000),
            'contact_name' => fake()->name(),
            'contact_phone' => '233'.fake()->numerify('#########'),
        ]);

        // Generate invalid request data (at least one validation error)
        $invalidDataTypes = [
            'missing_description',
            'invalid_phone',
            'invalid_email',
            'description_too_long',
            'customer_name_too_long',
        ];

        $invalidType = fake()->randomElement($invalidDataTypes);

        $requestData = match ($invalidType) {
            'missing_description' => [
                // description is required
                'customer_name' => fake()->name(),
            ],
            'invalid_phone' => [
                'description' => fake()->sentence(),
                'customer_phone' => fake()->randomElement([
                    '0241234567', // Wrong format (starts with 0)
                    '233123', // Too short
                    '23312345678901234', // Too long
                    'invalid-phone', // Non-numeric
                    '234123456789', // Wrong country code
                ]),
            ],
            'invalid_email' => [
                'description' => fake()->sentence(),
                'customer_email' => fake()->randomElement([
                    'not-an-email',
                    '@nodomain.com',
                    'spaces in@email.com',
                    'double@@email.com',
                ]),
            ],
            'description_too_long' => [
                'description' => str_repeat('a', 501), // Definitely over 500 characters
            ],
            'customer_name_too_long' => [
                'description' => fake()->sentence(),
                'customer_name' => str_repeat('b', 256), // Definitely over 255 characters
            ],
        };

        // Make request
        $response = $isAuthenticated
            ? $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", $requestData)
            : $this->postJson("/api/v1/orders/{$order->id}/payments/hubtel/initiate", $requestData);

        // Property: Validation errors MUST return HTTP 422
        $response->assertStatus(422);

        $json = $response->json();

        // Property: Response MUST have 'errors' field with field-specific messages
        expect($json)->toHaveKey('errors');
        expect($json['errors'])->toBeArray();
        expect($json['errors'])->not->toBeEmpty();

        // Property: Each error field MUST have an array of error messages
        foreach ($json['errors'] as $field => $messages) {
            expect($field)->toBeString();
            expect($messages)->toBeArray();
            expect($messages)->not->toBeEmpty();

            // Property: Each error message MUST be a string
            foreach ($messages as $message) {
                expect($message)->toBeString();
                expect($message)->not->toBeEmpty();
            }
        }

        // Property: Specific validation errors MUST be present for the invalid data type
        match ($invalidType) {
            'missing_description' => expect($json['errors'])->toHaveKey('description'),
            'invalid_phone' => expect($json['errors'])->toHaveKey('customer_phone'),
            'invalid_email' => expect($json['errors'])->toHaveKey('customer_email'),
            'description_too_long' => expect($json['errors'])->toHaveKey('description'),
            'customer_name_too_long' => expect($json['errors'])->toHaveKey('customer_name'),
        };
    }
})->group('property', 'hubtel', 'validation');
