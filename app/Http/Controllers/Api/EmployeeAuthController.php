<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeLoginRequest;
use App\Http\Requests\PosLoginRequest;
use App\Http\Resources\EmployeeAuthResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            $value = str_starts_with($digits, '233') ? $digits : '233'.ltrim($digits, '0');
        }

        if (! Auth::attempt([$field => $value, 'password' => $password])) {
            if ($field === 'phone' && $value !== $identifier) {
                if (! Auth::attempt(['phone' => $identifier, 'password' => $password])) {
                    return response()->unauthorized();
                }
            } else {
                return response()->unauthorized();
            }
        }

        $user = Auth::user();

        if (! $user->employee) {
            Auth::logout();

            return response()->forbidden('User is not an employee');
        }

        if ($user->employee->status !== EmployeeStatus::Active) {
            Auth::logout();

            return response()->forbidden('Employee account is inactive');
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
     * POS login with 4-digit PIN.
     */
    public function posLogin(PosLoginRequest $request): JsonResponse
    {
        $employee = Employee::query()
            ->where('pos_pin', $request->pin)
            ->with(['user', 'branches'])
            ->first();

        if (! $employee || $employee->status !== EmployeeStatus::Active) {
            return response()->forbidden('Invalid PIN or inactive account');
        }

        $user = $employee->user;
        $token = $user->createToken('pos-auth-token')->plainTextToken;

        $roles = $user->getRoleNames();
        $role = $roles->first() ?? 'employee';

        $firstBranch = $employee->branches->first();
        $staffUser = [
            'id' => (string) $employee->id,
            'name' => $user->name,
            'role' => $role,
            'branch' => $firstBranch?->name ?? '',
            'branchId' => (string) ($firstBranch?->id ?? ''),
            'branchIds' => $employee->branches->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
        ];

        activity('auth')
            ->causedBy($user)
            ->performedOn($employee)
            ->event('pos_login')
            ->withProperties(['branch' => $firstBranch?->name])
            ->log("POS login: {$user->name} at ".($firstBranch?->name ?? 'N/A'));

        return response()->success([
            'token' => $token,
            'user' => $staffUser,
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
        activity('auth')
            ->causedBy($request->user())
            ->event('staff_logout')
            ->log('Staff logout');

        $request->user()->tokens()->delete();

        return response()->deleted();
    }
}
