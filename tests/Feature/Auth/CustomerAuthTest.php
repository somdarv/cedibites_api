<?php

use App\Models\Customer;
use App\Models\Otp;
use App\Models\User;
use App\Notifications\OtpNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->phone = '+233'.fake()->unique()->numerify('#########');
});

describe('Send OTP', function () {
    test('sends OTP successfully', function () {
        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->phone,
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'message' => 'OTP sent successfully',
                ],
            ]);

        $this->assertDatabaseHas('otps', [
            'phone' => $this->phone,
            'verified' => false,
        ]);
    });

    test('validates phone number is required', function () {
        $response = $this->postJson('/api/v1/auth/send-otp', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });

    test('validates phone number format', function () {
        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '0241234567',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });

    test('accepts valid Ghana phone format', function () {
        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+233501234567',
        ]);

        $response->assertOk();
    });

    test('rejects duplicate email when provided', function () {
        User::factory()->create(['email' => 'existing@example.com', 'phone' => $this->phone]);

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+233501234568',
            'email' => 'existing@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    test('accepts optional email when unique', function () {
        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->phone,
            'email' => 'newuser@example.com',
        ]);

        $response->assertOk();
    });

    test('sends OTP to email when email is provided', function () {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->phone,
            'email' => 'user@example.com',
        ]);

        $response->assertOk();
        Notification::assertSentOnDemand(OtpNotification::class);
    });
});

describe('Verify OTP', function () {
    test('verifies OTP for existing user and returns token', function () {
        $user = User::factory()->create(['phone' => $this->phone]);
        Customer::factory()->create(['user_id' => $user->id]);

        Otp::create([
            'phone' => $this->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
            'verified' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $this->phone,
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'customer',
                    ],
                ],
            ]);

        expect($response->json('data.token'))->toBeString();
        expect($response->json('data.user.phone'))->toBe($this->phone);
    });

    test('verifies OTP for new user and returns registration flag', function () {
        Otp::create([
            'phone' => $this->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
            'verified' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $this->phone,
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'requires_registration' => true,
                    'phone' => $this->phone,
                ],
            ]);
    });

    test('rejects invalid OTP', function () {
        Otp::create([
            'phone' => $this->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
            'verified' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $this->phone,
            'otp' => '999999',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Invalid or expired OTP',
            ]);
    });

    test('rejects expired OTP', function () {
        Otp::create([
            'phone' => $this->phone,
            'otp' => '123456',
            'expires_at' => now()->subMinutes(10),
            'verified' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $this->phone,
            'otp' => '123456',
        ]);

        $response->assertUnprocessable();
    });

    test('validates OTP is required', function () {
        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $this->phone,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);
    });

    test('validates OTP is 6 digits', function () {
        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $this->phone,
            'otp' => '12345',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);
    });
});

describe('Register', function () {
    test('registers new user successfully', function () {
        $otp = Otp::create([
            'phone' => $this->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
            'verified' => true,
        ]);

        // Manually set created_at to 5 minutes ago
        $otp->created_at = now()->subMinutes(5);
        $otp->saveQuietly();

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => $this->phone,
            'name' => 'Kwame Mensah',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'phone',
                        'customer',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => $this->phone,
            'name' => 'Kwame Mensah',
        ]);

        $user = User::where('phone', $this->phone)->first();
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'is_guest' => false,
        ]);
    });

    test('rejects registration without recent OTP verification', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => $this->phone,
            'name' => 'Kwame Mensah',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'OTP verification required',
            ]);
    });

    test('rejects registration with old OTP verification', function () {
        $otp = Otp::create([
            'phone' => $this->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
            'verified' => true,
        ]);

        // Manually set created_at to 11 minutes ago (outside 10-minute window)
        $otp->created_at = now()->subMinutes(11);
        $otp->saveQuietly();

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => $this->phone,
            'name' => 'Kwame Mensah',
        ]);

        $response->assertUnprocessable();
    });

    test('rejects duplicate phone number', function () {
        User::factory()->create(['phone' => $this->phone]);

        Otp::create([
            'phone' => $this->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
            'verified' => true,
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => $this->phone,
            'name' => 'Kwame Mensah',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });

    test('validates name is required', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => $this->phone,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('registers with optional email when provided', function () {
        Otp::create([
            'phone' => $this->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
            'verified' => true,
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => $this->phone,
            'name' => 'Kwame Mensah',
            'email' => 'kwame@example.com',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'phone' => $this->phone,
            'email' => 'kwame@example.com',
        ]);
    });
});

describe('Get Authenticated User', function () {
    test('returns authenticated user', function () {
        $user = User::factory()->create(['phone' => $this->phone]);
        Customer::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'phone',
                    'email',
                    'customer',
                ],
            ])
            ->assertJson([
                'data' => [
                    'phone' => $this->phone,
                ],
            ]);
    });

    test('rejects unauthenticated request', function () {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertUnauthorized();
    });
});

describe('Logout', function () {
    test('logs out user successfully', function () {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/logout');

        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    });

    test('rejects unauthenticated logout', function () {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    });
});
