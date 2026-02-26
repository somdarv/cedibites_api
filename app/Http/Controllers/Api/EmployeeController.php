<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['user.roles', 'branch']);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->latest()->paginate($request->per_page ?? 15);

        return response()->success(
            EmployeeResource::collection($employees)->response()->getData(true),
            'Employees retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Create employee
            $employee = Employee::create([
                'user_id' => $user->id,
                'branch_id' => $request->branch_id,
                'employee_no' => 'EMP'.str_pad(Employee::count() + 1, 5, '0', STR_PAD_LEFT),
                'hire_date' => $request->hire_date ?? now(),
                'status' => $request->status ?? EmployeeStatus::Active->value,
            ]);

            DB::commit();

            return response()->success(
                new EmployeeResource($employee->load(['user.roles', 'branch'])),
                'Employee created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->error('Failed to create employee: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): JsonResponse
    {
        return response()->success(
            new EmployeeResource($employee->load(['user.roles', 'branch'])),
            'Employee retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Update user
            $userData = [];
            if ($request->has('name')) {
                $userData['name'] = $request->name;
            }
            if ($request->has('email')) {
                $userData['email'] = $request->email;
            }
            if ($request->has('phone')) {
                $userData['phone'] = $request->phone;
            }

            if (! empty($userData)) {
                $employee->user->update($userData);
            }

            // Update role if provided
            if ($request->has('role')) {
                $employee->user->syncRoles([$request->role]);
            }

            // Update employee
            $employeeData = [];
            if ($request->has('branch_id')) {
                $employeeData['branch_id'] = $request->branch_id;
            }
            if ($request->has('status')) {
                $employeeData['status'] = $request->status;
            }

            if (! empty($employeeData)) {
                $employee->update($employeeData);
            }

            DB::commit();

            return response()->success(
                new EmployeeResource($employee->fresh(['user.roles', 'branch'])),
                'Employee updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->error('Failed to update employee: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        // Soft delete by setting status to inactive
        $employee->update(['status' => EmployeeStatus::Inactive->value]);

        return response()->success(null, 'Employee deactivated successfully.');
    }
}
