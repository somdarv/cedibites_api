<?php

namespace App\Http\Controllers\Api;

use App\Events\CustomerSessionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\SendOTPRequest;
use App\Http\Requests\VerifyOTPRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\OtpNotification;
use App\Notifications\WelcomeNotification;
use App\Services\HubtelSmsService;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AuthController extends Controller
{
    public function __construct(
        protected OTPService $otpService,
        protected HubtelSmsService $smsService
    ) {}

    /**
     * Send OTP to phone number.
     */
    public function sendOTP(SendOTPRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $phone = $validated['phone'];
            $email = $validated['email'] ?? null;
            $otp = $this->otpService->generate();

            $this->otpService->store($phone, $otp, $request->ip());

            $message = "Your CediBites verification code is: {$otp}. Valid for 5 minutes.";
            // Strip the + prefix for Hubtel API
            $hubtelPhone = ltrim($phone, '+');
            $result = $this->smsService->sendSingle($hubtelPhone, $message);
            $smsSent = isset($result['messageId']);

            if (! $smsSent) {
                \Log::error('Failed to send OTP SMS', ['phone' => $phone]);
            }

            if ($email) {
                try {
                    Notification::route('mail', $email)->notify(new OtpNotification($otp));
                } catch (\Exception $e) {
                    \Log::error('Failed to send OTP email', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->success([
                'message' => 'OTP sent successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('OTP send failed', [
                'phone' => $request->input('phone'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->server_error('Failed to send OTP. Please try again.');
        }
    }

    /**
     * Verify OTP and return user or registration flag.
     */
    public function verifyOTP(VerifyOTPRequest $request): JsonResponse|Response
    {
        $validated = $request->validated();
        $phone = $validated['phone'];
        $otp = $validated['otp'];

        $otpRecord = $this->otpService->verify($phone, $otp);

        if (! $otpRecord) {
            return response()->unprocessable('Invalid or expired OTP', [
                'otp' => ['The provided OTP is invalid or has expired'],
            ]);
        }

        $user = User::where('phone', $phone)->first();

        if ($user) {
            if (! $user->customer) {
                Customer::create([
                    'user_id' => $user->id,
                    'is_guest' => false,
                ]);
                $user->load('customer');
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->success([
                'token' => $token,
                'user' => new AuthUserResource($user->load(['customer', 'roles.permissions'])),
            ]);
        }

        return response()->success([
            'requires_registration' => true,
            'phone' => $phone,
        ]);
    }

    /**
     * Register new user.
     */
    public function register(RegisterRequest $request): JsonResponse|Response
    {
        $validated = $request->validated();
        $phone = $validated['phone'];

        if (! $this->otpService->hasRecentlyVerified($phone)) {
            return response()->unprocessable('OTP verification required', [
                'phone' => ['Please verify your phone number first'],
            ]);
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $validated['name'],
                'phone' => $phone,
                'email' => $validated['email'] ?? null,
            ]);

            Customer::create([
                'user_id' => $user->id,
                'is_guest' => false,
            ]);

            DB::commit();

            // Send welcome notification
            $user->notify(new WelcomeNotification);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->created([
                'token' => $token,
                'user' => new AuthUserResource($user->load(['customer', 'roles.permissions'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->server_error();
        }
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load(['customer', 'roles.permissions']);

        if (! $user->customer) {
            Customer::create([
                'user_id' => $user->id,
                'is_guest' => false,
            ]);
            $user->load('customer');
        }

        return response()->success(new AuthUserResource($user));
    }

    /**
     * Quick registration after order placement (no OTP required).
     * This allows customers who just placed an order to save their info.
     */
    public function quickRegister(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Allow phone if no password-having user owns it already.
            // POS-created users (no password) are merged rather than rejected.
            'phone' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('users', 'phone')->where(fn ($q) => $q->whereNotNull('password'))],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        try {
            DB::beginTransaction();

            // Find an existing POS-created user (no password) or create a new one.
            $user = User::where('phone', $validated['phone'])->first();
            if (! $user) {
                $user = User::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                ]);
            }

            // Ensure a customer record exists and is marked as not a guest.
            if (! $user->customer) {
                Customer::create([
                    'user_id' => $user->id,
                    'is_guest' => false,
                ]);
            } else {
                $user->customer->update(['is_guest' => false]);
            }

            DB::commit();

            // Send welcome notification (don't fail if notification fails)
            try {
                $user->notify(new WelcomeNotification);
            } catch (\Exception $e) {
                \Log::warning('Failed to send welcome notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->created([
                'token' => $token,
                'user' => new AuthUserResource($user->load(['customer', 'roles.permissions'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->server_error();
        }
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse|Response
    {
        $user = $request->user();

        CustomerSessionEvent::dispatch($user);

        $user->tokens()->delete();

        return response()->deleted();
    }
}
