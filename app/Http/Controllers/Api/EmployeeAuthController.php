<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeLoginRequest;
use App\Http\Resources\EmployeeAuthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class EmployeeAuthController extends Controller
{
    /**
     * Employee login with email and password.
     */
    public function login(EmployeeLoginRequest $request): JsonResponse|Response
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            return response()->unauthorized();
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

        return response()->success([
            'token' => $token,
            'user' => new EmployeeAuthResource($user->load(['employee.branch', 'roles', 'permissions'])),
        ]);
    }

    /**
     * Employee logout.
     */
    public function logout(Request $request): Response
    {
        $request->user()->tokens()->delete();

        return response()->deleted();
    }
}
