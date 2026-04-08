<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Events\StaffSessionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeLoginRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\EmployeeAuthResource;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\StaffPasswordResetNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeAuthController extends Controller
{
    /**
     * Employee login with identifier (email or phone) and password.
     */
    public function login(EmployeeLoginRequest $request): JsonResponse|Response
    {
        $identifier = trim($request->identifier);
        $password = $request->password;

        $field = str_contains($identifier, '@') ? 'email' : 'phone';
        $value = $identifier;

        if ($field === 'phone') {
            $digits = preg_replace('/\D/', '', $identifier);
            $normalized = str_starts_with($digits, '233') ? $digits : '233'.ltrim($digits, '0');
            $value = '+'.ltrim($normalized, '+');
        }

        if (! Auth::attempt([$field => $value, 'password' => $password])) {
            if ($field === 'phone' && $value !== $identifier) {
                if (! Auth::attempt(['phone' => $identifier, 'password' => $password])) {
                    activity('auth')
                        ->withProperties(['identifier' => $identifier, 'ip' => $request->ip()])
                        ->event('staff_login_failed')
                        ->log("Failed staff login attempt for: {$identifier}");

                    return response()->unauthorized('The credentials you entered are incorrect. Please try again.', 'invalid_credentials');
                }
            } else {
                activity('auth')
                    ->withProperties(['identifier' => $identifier, 'ip' => $request->ip()])
                    ->event('staff_login_failed')
                    ->log("Failed staff login attempt for: {$identifier}");

                return response()->unauthorized('The credentials you entered are incorrect. Please try again.', 'invalid_credentials');
            }
        }

        $user = Auth::user();

        if (! $user->employee) {
            Auth::logout();

            return response()->forbidden('User is not an employee');
        }

        if ($user->employee->status !== EmployeeStatus::Active) {
            Auth::logout();

            return response()->forbidden('Your account is currently '.$user->employee->status->value.'. Please contact your administrator.');
        }

        $token = $user->createToken('employee-auth-token')->plainTextToken;

        activity('auth')
            ->causedBy($user)
            ->event('staff_login')
            ->log("Staff login: {$user->name} (".$user->employee->branches->pluck('name')->join(', ').')');

        return response()->success([
            'token' => $token,
            'user' => new EmployeeAuthResource($user->load(['employee.branches', 'roles', 'permissions'])),
        ]);
    }

    /**
     * Return the currently authenticated employee's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['employee.branches', 'roles', 'permissions']);

        return response()->success([
            'user' => new EmployeeAuthResource($user),
        ]);
    }

    /**
     * Change password and clear the must_reset_password flag.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'recoverable_password' => $request->password,
            'must_reset_password' => false,
            'password_reset_required_at' => null,
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * Employee logout.
     */
    public function logout(Request $request): Response
    {
        $user = $request->user();

        activity('auth')
            ->causedBy($user)
            ->event('staff_logout')
            ->log('Staff logout');

        StaffSessionEvent::dispatch($user, 'session.revoked');

        $user->tokens()->delete();

        return response()->deleted();
    }

    /**
     * Send a password reset link to the staff member's phone (and email if present).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $identifier = trim($request->string('identifier'));

        $user = $this->findUserByIdentifier($identifier);

        // Always return success to prevent user enumeration
        if (! $user || ! $user->employee) {
            return response()->success(['message' => 'If an account exists, you will receive a reset link shortly.']);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->upsert(
            [
                'email' => $identifier,
                'token' => Hash::make($token),
                'created_at' => now(),
            ],
            uniqueBy: ['email'],
            update: ['token', 'created_at'],
        );

        $resetLink = config('app.frontend_url').'/staff/reset-password?token='.urlencode($token).'&identifier='.urlencode($identifier);

        $user->notify(new StaffPasswordResetNotification($resetLink));

        return response()->success(['message' => 'If an account exists, you will receive a reset link shortly.']);
    }

    /**
     * Reset the staff member's password using a valid token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $identifier = trim($request->string('identifier'));

        $record = DB::table('password_reset_tokens')->where('email', $identifier)->first();

        if (! $record || ! Hash::check($request->string('token'), $record->token)) {
            return response()->json(['message' => 'This reset link is invalid or has already been used.'], 422);
        }

        if (Carbon::parse($record->created_at)->addHour()->isPast()) {
            DB::table('password_reset_tokens')->where('email', $identifier)->delete();

            return response()->json(['message' => 'This reset link has expired. Please request a new one.'], 422);
        }

        $user = $this->findUserByIdentifier($identifier);

        if (! $user || ! $user->employee) {
            return response()->json(['message' => 'This reset link is invalid or has already been used.'], 422);
        }

        $user->update([
            'password' => Hash::make($request->string('password')),
            'recoverable_password' => $request->string('password')->toString(),
            'must_reset_password' => false,
            'password_reset_required_at' => null,
        ]);

        DB::table('password_reset_tokens')->where('email', $identifier)->delete();

        return response()->success(['message' => 'Password reset successfully. You can now log in.']);
    }

    /**
     * Find a User by email or normalised Ghana phone number.
     */
    private function findUserByIdentifier(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return User::where('email', $identifier)->first();
        }

        $digits = preg_replace('/\D/', '', $identifier);
        $phone = str_starts_with($digits, '233') ? $digits : '233'.ltrim($digits, '0');

        return User::where('phone', $phone)->orWhere('phone', $identifier)->first();
    }
}
