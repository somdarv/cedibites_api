<?php

use App\Models\User;

test('quick register creates user and customer without OTP', function () {
    $response = $this->postJson('/api/v1/auth/quick-register', [
        'name' => 'John Doe',
        'phone' => '+233241234567',
        'email' => 'john@example.com',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'phone',
                    'email',
                ],
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'phone' => '+233241234567',
        'email' => 'john@example.com',
    ]);

    $user = User::where('phone', '+233241234567')->first();
    expect($user)->not->toBeNull();
    expect($user->customer)->not->toBeNull();
    expect($user->customer->is_guest)->toBeFalse();
});

test('quick register fails if phone already exists', function () {
    User::factory()->create([
        'phone' => '+233241234567',
    ]);

    $response = $this->postJson('/api/v1/auth/quick-register', [
        'name' => 'John Doe',
        'phone' => '+233241234567',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

test('quick register requires name and phone', function () {
    $response = $this->postJson('/api/v1/auth/quick-register', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'phone']);
});

test('quick register works without email', function () {
    $response = $this->postJson('/api/v1/auth/quick-register', [
        'name' => 'Jane Doe',
        'phone' => '+233249876543',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'name' => 'Jane Doe',
        'phone' => '+233249876543',
        'email' => null,
    ]);
});
