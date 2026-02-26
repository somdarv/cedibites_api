<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\SendOTPRequest;
use App\Http\Requests\VerifyOTPRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\OTPService;
use App\Services\SMSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function __construct(
        protected OTPService $otpService,
        protected SMSService $smsService
    ) {}

    /**
     * Send OTP to phone number.
     */
    public function sendOTP(SendOTPRequest $request): JsonResponse
    {
        try {
            $phone = $request->validated()['phone'];
            $otp = $this->otpService->generate();

            $this->otpService->store($phone, $otp, $request->ip());
            $smsSent = $this->smsService->sendOTP($phone, $otp);

            if (! $smsSent) {
                \Log::error('Failed to send OTP SMS', ['phone' => $phone]);
            }

            return response()->success([
                'message' => 'OTP sent successfully',
                'dev_otp' => app()->environment('local') ? $otp : null,
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
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->success([
                'token' => $token,
                'user' => new AuthUserResource($user->load('customer')),
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
                'user' => new AuthUserResource($user->load('customer')),
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
        return response()->success(
            new AuthUserResource($request->user()->load('customer'))
        );
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): Response
    {
        $request->user()->tokens()->delete();

        return response()->deleted();
    }
}
