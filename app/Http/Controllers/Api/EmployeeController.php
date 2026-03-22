<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\PasswordResetRequiredNotification;
use App\Notifications\StaffAccountCreatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['user.roles.permissions', 'user.permissions', 'branches']);

        if ($request->has('branch_id')) {
            $query->whereHas('branches', function ($q) use ($request) {
                $q->where('branches.id', $request->branch_id);
            });
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
    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $password = $request->filled('password')
                ? $request->password
                : Str::password(12);

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($password),
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Assign individual permissions if provided
            if ($request->has('permissions') && is_array($request->permissions)) {
                $user->givePermissionTo($request->permissions);
            }

            // Create employee with all fields — derive next number from the
            // highest existing suffix inside the transaction to avoid races.
            $maxNo = Employee::lockForUpdate()
                ->where('employee_no', 'like', 'EMP%')
                ->max(DB::raw('CAST(SUBSTRING(employee_no, 4) AS UNSIGNED)'));
            $nextNo = 'EMP'.str_pad((int) $maxNo + 1, 5, '0', STR_PAD_LEFT);

            $employeeData = [
                'user_id' => $user->id,
                'employee_no' => $nextNo,
                'hire_date' => $request->hire_date ?? now(),
                'status' => $request->status ?? EmployeeStatus::Active->value,
            ];

            // Add optional fields if provided
            $optionalFields = [
                'pos_pin', 'ssnit_number', 'ghana_card_id', 'tin_number',
                'date_of_birth', 'nationality', 'emergency_contact_name',
                'emergency_contact_phone', 'emergency_contact_relationship',
            ];

            foreach ($optionalFields as $field) {
                if ($request->filled($field)) {
                    $employeeData[$field] = $request->$field;
                }
            }

            $employee = Employee::create($employeeData);
            $employee->branches()->sync($request->branch_ids);

            DB::commit();

            $user->notify(new StaffAccountCreatedNotification($password));

            return response()->created(
                new EmployeeResource($employee->load(['user.roles.permissions', 'user.permissions', 'branches']))
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
            new EmployeeResource($employee->load(['user.roles.permissions', 'user.permissions', 'branches'])),
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
                $oldRole = $employee->user->getRoleNames()->first();
                $employee->user->syncRoles([$request->role]);
                if ($oldRole !== $request->role) {
                    activity('admin')
                        ->causedBy($request->user())
                        ->performedOn($employee)
                        ->event('role_changed')
                        ->withProperties([
                            'employee_name' => $employee->user->name,
                            'old_role' => $oldRole,
                            'new_role' => $request->role,
                        ])
                        ->log("Role changed: {$employee->user->name} from {$oldRole} to {$request->role}");
                }
            }

            // Update individual permissions if provided
            if ($request->has('permissions') && is_array($request->permissions)) {
                $employee->user->syncPermissions($request->permissions);
            }

            // Update employee basic fields
            $employeeData = [];
            if ($request->has('status')) {
                $employeeData['status'] = $request->status;
            }
            if ($request->has('hire_date')) {
                $employeeData['hire_date'] = $request->hire_date;
            }

            // Update HR fields if provided
            $hrFields = [
                'pos_pin', 'ssnit_number', 'ghana_card_id', 'tin_number',
                'date_of_birth', 'nationality', 'emergency_contact_name',
                'emergency_contact_phone', 'emergency_contact_relationship',
            ];

            foreach ($hrFields as $field) {
                if ($request->has($field)) {
                    $employeeData[$field] = $request->$field;
                }
            }

            if (! empty($employeeData)) {
                $employee->update($employeeData);
            }

            // Update branch assignments
            if ($request->has('branch_ids')) {
                $employee->branches()->sync($request->branch_ids);
            }

            DB::commit();

            return response()->success(
                new EmployeeResource($employee->fresh(['user.roles.permissions', 'user.permissions', 'branches'])),
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
        // Soft delete by setting status to suspended
        $employee->update(['status' => EmployeeStatus::Suspended->value]);

        return response()->success(null, 'Employee deactivated successfully.');
    }

    /**
     * Force logout an employee by revoking all their tokens.
     */
    public function forceLogout(Employee $employee): JsonResponse
    {
        $employee->user->tokens()->delete();

        activity('admin')
            ->causedBy(request()->user())
            ->performedOn($employee)
            ->event('force_logout')
            ->withProperties([
                'employee_name' => $employee->user->name,
                'employee_id' => $employee->id,
            ])
            ->log("Forced logout: {$employee->user->name}");

        return response()->json([
            'message' => 'Employee logged out successfully.',
        ]);
    }

    /**
     * Require password reset for an employee.
     */
    public function requirePasswordReset(Employee $employee): JsonResponse
    {
        $employee->user->update([
            'must_reset_password' => true,
            'password_reset_required_at' => now(),
        ]);

        $employee->user->notify(new PasswordResetRequiredNotification);

        activity('admin')
            ->causedBy(request()->user())
            ->performedOn($employee)
            ->event('password_reset_required')
            ->withProperties([
                'employee_name' => $employee->user->name,
                'employee_id' => $employee->id,
            ])
            ->log("Password reset required: {$employee->user->name}");

        return response()->json([
            'message' => 'Password reset required successfully.',
        ]);
    }
}
