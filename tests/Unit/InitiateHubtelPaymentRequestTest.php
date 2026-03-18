<?php

use App\Http\Requests\InitiateHubtelPaymentRequest;
use App\Models\Payment;
use Illuminate\Support\Facades\Validator;

test('validation passes with valid data', function () {
    $data = [
        'customer_name' => 'John Doe',
        'customer_phone' => '233244123456',
        'customer_email' => 'john@example.com',
        'description' => 'Payment for Order #ORD-123456',
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('validation passes with minimal required data', function () {
    $data = [
        'description' => 'Payment for Order #ORD-123456',
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('validation fails with invalid phone format', function () {
    $data = [
        'customer_phone' => '0244123456', // Invalid format (should start with 233)
        'description' => 'Payment for Order #ORD-123456',
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('customer_phone'))->toBeTrue();
});

test('validation fails with phone number too short', function () {
    $data = [
        'customer_phone' => '23324412345', // Too short (should be 12 digits)
        'description' => 'Payment for Order #ORD-123456',
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('customer_phone'))->toBeTrue();
});

test('validation fails with invalid email format', function () {
    $data = [
        'customer_email' => 'invalid-email',
        'description' => 'Payment for Order #ORD-123456',
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('customer_email'))->toBeTrue();
});

test('validation fails with missing description', function () {
    $data = [
        'customer_name' => 'John Doe',
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('description'))->toBeTrue();
});

test('validation fails with description exceeding max length', function () {
    $data = [
        'description' => str_repeat('a', 501), // Exceeds 500 character limit
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('description'))->toBeTrue();
});

test('validation fails with customer name exceeding max length', function () {
    $data = [
        'customer_name' => str_repeat('a', 256), // Exceeds 255 character limit
        'description' => 'Payment for Order #ORD-123456',
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('customer_name'))->toBeTrue();
});

test('validation fails with customer email exceeding max length', function () {
    $data = [
        'customer_email' => str_repeat('a', 246).'@example.com', // Exceeds 255 character limit (246 + 12 = 258)
        'description' => 'Payment for Order #ORD-123456',
    ];

    $request = new InitiateHubtelPaymentRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('customer_email'))->toBeTrue();
});

// Property 26: Input Validation Completeness
test('property: input validation enforces all required constraints', function () {
    // For any payment initiation request, validation SHALL enforce:
    // - description is non-empty
    // - payeeMobileNumber (if provided) matches format 233XXXXXXXXX
    // - payeeEmail (if provided) is valid email format

    $request = new InitiateHubtelPaymentRequest;

    // Test 1: Description must be non-empty
    $emptyDescriptionData = ['description' => ''];
    $validator = Validator::make($emptyDescriptionData, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('description'))->toBeTrue();

    // Test 2: Valid phone format passes (233 followed by 9 digits)
    $validPhoneData = [
        'customer_phone' => '233'.fake()->numerify('#########'),
        'description' => 'Test payment',
    ];
    $validator = Validator::make($validPhoneData, $request->rules());
    expect($validator->passes())->toBeTrue();

    // Test 3: Invalid phone format fails (not starting with 233)
    $invalidPhoneData = [
        'customer_phone' => '024'.fake()->numerify('#######'),
        'description' => 'Test payment',
    ];
    $validator = Validator::make($invalidPhoneData, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('customer_phone'))->toBeTrue();

    // Test 4: Valid email format passes
    $validEmailData = [
        'customer_email' => fake()->safeEmail(),
        'description' => 'Test payment',
    ];
    $validator = Validator::make($validEmailData, $request->rules());
    expect($validator->passes())->toBeTrue();

    // Test 5: Invalid email format fails
    $invalidEmailData = [
        'customer_email' => 'not-an-email',
        'description' => 'Test payment',
    ];
    $validator = Validator::make($invalidEmailData, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('customer_email'))->toBeTrue();
})->repeat(100);
