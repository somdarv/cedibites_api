<?php

use App\Models\Order;
use App\Services\HubtelService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.hubtel.client_id' => 'test_client_id',
        'services.hubtel.client_secret' => 'test_client_secret',
        'services.hubtel.merchant_account_number' => 'HM12345',
        'app.frontend_url' => 'https://example.com',
    ]);
});

test('property: URL generation from routes', function () {
    // **Property 21: URL Generation from Routes**
    // **Validates: Requirements 10.8**

    $order = Order::factory()->create([
        'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
        'total_amount' => fake()->randomFloat(2, 10, 1000),
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

    $service = new HubtelService;

    $service->initializeTransaction([
        'order' => $order,
        'description' => 'Test payment for order '.$order->order_number,
    ]);

    // Verify HTTP request was made with URLs generated from routes and config
    Http::assertSent(function ($request) use ($order) {
        $data = $request->data();

        // Verify callbackUrl is generated from named route
        $callbackUrlValid = isset($data['callbackUrl'])
            && str_contains($data['callbackUrl'], '/payments/hubtel/callback');

        // Verify returnUrl is generated from app config
        $returnUrlValid = isset($data['returnUrl'])
            && str_starts_with($data['returnUrl'], config('app.frontend_url'))
            && str_contains($data['returnUrl'], $order->order_number);

        // Verify cancellationUrl is generated from app config
        $cancellationUrlValid = isset($data['cancellationUrl'])
            && str_starts_with($data['cancellationUrl'], config('app.frontend_url'))
            && str_contains($data['cancellationUrl'], $order->order_number);

        return $callbackUrlValid && $returnUrlValid && $cancellationUrlValid;
    });
})->repeat(100);
