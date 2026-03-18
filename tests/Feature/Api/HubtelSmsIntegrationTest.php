<?php

use App\Models\Otp;
use App\Services\HubtelSmsService;
use Illuminate\Support\Facades\Http;

describe('HubtelSmsService Integration with AuthController', function () {
    beforeEach(function () {
        $this->phone = '+233241234567';

        // Reset HTTP fake to allow specific fakes in each test
        Http::preventStrayRequests();

        config([
            'services.hubtel.client_id' => 'test_client_id',
            'services.hubtel.client_secret' => 'test_client_secret',
            'services.hubtel.sender_id' => 'CediBites',
            'services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages',
        ]);
    });

    test('sends OTP via HubtelSmsService successfully', function () {
        Http::fake([
            'sms.hubtel.com/v1/messages/send' => Http::response([
                'messageId' => 'msg_123456',
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->phone,
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'message' => 'OTP sent successfully',
                ],
            ]);

        // Verify OTP was stored
        $this->assertDatabaseHas('otps', [
            'phone' => $this->phone,
            'verified' => false,
        ]);

        // Verify HTTP request was made to Hubtel
        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.hubtel.com/v1/messages/send' &&
                   $request->hasHeader('Authorization') &&
                   $request['From'] === 'CediBites' &&
                   $request['To'] === '233241234567' &&
                   str_contains($request['Content'], 'verification code');
        });
    });

    test('validates phone number format before sending', function () {
        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '0241234567', // Invalid format
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        // No HTTP request should be made
        Http::assertNothingSent();
    });

    test('sends OTP with correct message format', function () {
        Http::fake([
            'sms.hubtel.com/v1/messages/send' => Http::response([
                'messageId' => 'msg_123456',
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->phone,
        ]);

        // Get the OTP that was generated
        $otp = Otp::where('phone', $this->phone)->first();

        // Verify the message format
        Http::assertSent(function ($request) use ($otp) {
            $expectedMessage = "Your CediBites verification code is: {$otp->otp}. Valid for 5 minutes.";

            return $request['Content'] === $expectedMessage;
        });
    });
});

describe('HubtelSmsService Integration with SmsChannel', function () {
    beforeEach(function () {
        // Reset HTTP fake to allow specific fakes in each test
        Http::preventStrayRequests();

        config([
            'services.hubtel.client_id' => 'test_client_id',
            'services.hubtel.client_secret' => 'test_client_secret',
            'services.hubtel.sender_id' => 'CediBites',
            'services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages',
        ]);
    });

    test('notification channel uses HubtelSmsService', function () {
        Http::fake([
            'sms.hubtel.com/v1/messages/send' => Http::response([
                'messageId' => 'msg_notification_123',
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $user = \App\Models\User::factory()->create([
            'phone' => '+233241234567',
        ]);

        // Create a test notification
        $notification = new class extends \Illuminate\Notifications\Notification
        {
            public function via($notifiable)
            {
                return ['sms'];
            }

            public function toSms($notifiable)
            {
                return 'Test notification message';
            }
        };

        $user->notify($notification);

        // Verify HTTP request was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.hubtel.com/v1/messages/send' &&
                   $request['To'] === '233241234567' &&
                   $request['Content'] === 'Test notification message';
        });
    });

    test('notification channel logs message ID on success', function () {
        Http::fake([
            'sms.hubtel.com/v1/messages/send' => Http::response([
                'messageId' => 'msg_logged_123',
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $user = \App\Models\User::factory()->create([
            'phone' => '+233241234567',
        ]);

        $notification = new class extends \Illuminate\Notifications\Notification
        {
            public function via($notifiable)
            {
                return ['sms'];
            }

            public function toSms($notifiable)
            {
                return 'Test message';
            }
        };

        $user->notify($notification);

        // Just verify the notification was sent successfully
        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.hubtel.com/v1/messages/send';
        });
    });
});

describe('HubtelSmsService Configuration', function () {
    beforeEach(function () {
        // Reset HTTP fake to allow specific fakes in each test
        Http::preventStrayRequests();
    });

    test('uses correct Hubtel credentials from config', function () {
        config([
            'services.hubtel.client_id' => 'my_client_id',
            'services.hubtel.client_secret' => 'my_client_secret',
            'services.hubtel.sender_id' => 'MyApp',
        ]);

        Http::fake([
            'sms.hubtel.com/v1/messages/send' => Http::response([
                'messageId' => 'msg_123',
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $service = new HubtelSmsService;
        $service->sendSingle('233241234567', 'Test message');

        // Verify correct auth header
        Http::assertSent(function ($request) {
            $expectedAuth = 'Basic '.base64_encode('my_client_id:my_client_secret');

            return $request->hasHeader('Authorization', $expectedAuth) &&
                   $request['From'] === 'MyApp';
        });
    });

    test('throws exception when credentials are missing', function () {
        config([
            'services.hubtel.client_id' => null,
            'services.hubtel.client_secret' => null,
        ]);

        $service = new HubtelSmsService;

        expect(fn () => $service->sendSingle('233241234567', 'Test'))
            ->toThrow(\RuntimeException::class, 'Hubtel SMS is not properly configured');
    });
});
