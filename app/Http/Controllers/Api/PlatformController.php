<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\SmartErrorService;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PlatformController extends Controller
{
    public function __construct(
        private SystemHealthService $healthService,
        private SmartErrorService $errorService,
    ) {}

    /**
     * System health overview.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'data' => $this->healthService->check(),
        ]);
    }

    /**
     * Smart error feed — business-friendly error summaries.
     */
    public function errors(Request $request): JsonResponse
    {
        $limit = min((int) ($request->limit ?? 50), 100);

        return response()->json([
            'data' => $this->errorService->getFeed($limit),
        ]);
    }

    /**
     * Failed jobs list with retry/delete capability.
     */
    public function failedJobs(): JsonResponse
    {
        $jobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $firstLine = trim(explode("\n", $job->exception)[0]);

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'job' => class_basename($payload['displayName'] ?? 'Unknown'),
                    'queue' => $job->queue,
                    'error' => mb_substr($firstLine, 0, 200),
                    'failed_at' => $job->failed_at,
                ];
            });

        return response()->json(['data' => $jobs]);
    }

    /**
     * Retry a failed job.
     */
    public function retryJob(Request $request): JsonResponse
    {
        $this->verifyPasscode($request);

        $request->validate(['uuid' => ['required', 'string']]);

        Artisan::call('queue:retry', ['id' => [$request->uuid]]);

        activity('platform')
            ->causedBy($request->user())
            ->event('job_retried')
            ->withProperties(['uuid' => $request->uuid])
            ->log("Retried failed job: {$request->uuid}");

        return response()->json(['message' => 'Job queued for retry']);
    }

    /**
     * Reset a staff member's password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $this->verifyPasscode($request);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'new_password' => ['nullable', 'string', 'min:8'],
            'force_reset' => ['nullable', 'boolean'],
        ]);

        $employee = Employee::with('user')->findOrFail($validated['employee_id']);
        $user = $employee->user;

        // Generate a simple password if none provided
        $password = $validated['new_password'] ?? $this->generateSimplePassword();
        $forceReset = $validated['force_reset'] ?? false;

        $user->update([
            'password' => $password,
            'recoverable_password' => $password,
            'must_reset_password' => $forceReset,
            'password_reset_required_at' => $forceReset ? now() : null,
        ]);

        // Revoke all existing tokens so they must re-login
        $user->tokens()->delete();

        activity('platform')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('password_reset')
            ->withProperties(['employee_id' => $employee->id, 'employee_no' => $employee->employee_no, 'force_reset' => $forceReset])
            ->log("Platform admin reset password for {$user->name} ({$employee->employee_no})");

        return response()->json([
            'message' => 'Password reset successfully',
            'temporary_password' => $password,
            'must_reset' => $forceReset,
        ]);
    }

    /**
     * List all employees with their recoverable passwords (passcode-gated).
     */
    public function staffPasswords(Request $request): JsonResponse
    {
        $this->verifyPasscode($request);

        activity('platform')
            ->causedBy($request->user())
            ->event('passwords_viewed')
            ->log('Platform admin viewed staff password list');

        $employees = Employee::with(['user.roles', 'branches'])
            ->whereHas('user')
            ->get()
            ->map(fn (Employee $emp) => [
                'id' => $emp->id,
                'user_id' => $emp->user->id,
                'name' => $emp->user->name,
                'phone' => $emp->user->phone,
                'employee_no' => $emp->employee_no,
                'role' => $emp->user->getRoleNames()->first(),
                'branches' => $emp->branches->pluck('name'),
                'status' => $emp->status->value,
                'password' => $emp->user->recoverable_password,
                'has_password' => $emp->user->recoverable_password !== null,
                'must_reset_password' => $emp->user->must_reset_password,
            ]);

        return response()->json(['data' => $employees]);
    }

    /**
     * View a single employee's recoverable password (passcode-gated, logged).
     */
    public function viewPassword(Request $request): JsonResponse
    {
        $this->verifyPasscode($request);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        $employee = Employee::with('user')->findOrFail($validated['employee_id']);

        activity('platform')
            ->causedBy($request->user())
            ->performedOn($employee->user)
            ->event('password_viewed')
            ->withProperties(['employee_id' => $employee->id, 'employee_no' => $employee->employee_no])
            ->log("Platform admin viewed password for {$employee->user->name} ({$employee->employee_no})");

        return response()->json([
            'data' => [
                'employee_id' => $employee->id,
                'name' => $employee->user->name,
                'employee_no' => $employee->employee_no,
                'password' => $employee->user->recoverable_password,
                'has_password' => $employee->user->recoverable_password !== null,
                'must_reset_password' => $employee->user->must_reset_password,
            ],
        ]);
    }

    /**
     * List all platform admins.
     */
    public function listAdmins(): JsonResponse
    {
        $admins = User::role(Role::TechAdmin->value)
            ->with('employee')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->email,
                'employee_no' => $u->employee?->employee_no,
                'has_passcode' => $u->platform_passcode !== null,
                'created_at' => $u->created_at?->toIso8601String(),
                'last_login' => $u->tokens()->latest()->first()?->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $admins]);
    }

    /**
     * Promote an existing employee to platform admin.
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $this->verifyPasscode($request);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'new_passcode' => ['required', 'string', 'digits:6'],
        ]);

        $employee = Employee::with('user')->findOrFail($validated['employee_id']);
        $user = $employee->user;

        // Prevent self-escalation
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot modify your own platform admin status'], 422);
        }

        $user->assignRole(Role::TechAdmin->value);
        $user->update(['platform_passcode' => $validated['new_passcode']]);

        activity('platform')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('admin_created')
            ->withProperties(['employee_id' => $employee->id])
            ->log("Promoted {$user->name} to platform admin");

        return response()->json(['message' => "{$user->name} is now a platform admin"]);
    }

    /**
     * Revoke platform admin from a user.
     */
    public function revokeAdmin(Request $request, User $user): JsonResponse
    {
        $this->verifyPasscode($request);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot revoke your own platform admin access'], 422);
        }

        $user->removeRole(Role::TechAdmin->value);
        $user->update(['platform_passcode' => null]);

        activity('platform')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('admin_revoked')
            ->log("Revoked platform admin from {$user->name}");

        return response()->json(['message' => "Platform admin revoked from {$user->name}"]);
    }

    /**
     * Update the authenticated user's passcode.
     */
    public function updatePasscode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_passcode' => ['required', 'string', 'digits:6'],
            'new_passcode' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_passcode'], $user->platform_passcode)) {
            return response()->json(['message' => 'Current passcode is incorrect'], 422);
        }

        $user->update(['platform_passcode' => $validated['new_passcode']]);

        activity('platform')
            ->causedBy($user)
            ->event('passcode_changed')
            ->log('Platform admin changed their passcode');

        return response()->json(['message' => 'Passcode updated']);
    }

    /**
     * Clear application caches.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $this->verifyPasscode($request);

        $type = $request->validate(['type' => ['required', 'in:all,config,route,view,app']])['type'];

        match ($type) {
            'all' => collect(['cache:clear', 'config:clear', 'route:clear', 'view:clear'])
                ->each(fn ($cmd) => Artisan::call($cmd)),
            'config' => Artisan::call('config:clear'),
            'route' => Artisan::call('route:clear'),
            'view' => Artisan::call('view:clear'),
            'app' => Artisan::call('cache:clear'),
        };

        activity('platform')
            ->causedBy($request->user())
            ->event('cache_cleared')
            ->withProperties(['type' => $type])
            ->log("Cleared {$type} cache");

        return response()->json(['message' => "Cache ({$type}) cleared successfully"]);
    }

    /**
     * Toggle maintenance mode.
     */
    public function toggleMaintenance(Request $request): JsonResponse
    {
        $this->verifyPasscode($request);

        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
            $status = 'live';
        } else {
            Artisan::call('down', ['--secret' => bin2hex(random_bytes(16))]);
            $status = 'maintenance';
        }

        activity('platform')
            ->causedBy($request->user())
            ->event('maintenance_toggled')
            ->withProperties(['status' => $status])
            ->log("System set to {$status} mode");

        return response()->json([
            'message' => "System is now in {$status} mode",
            'status' => $status,
        ]);
    }

    /**
     * Active sessions overview.
     */
    public function activeSessions(): JsonResponse
    {
        $tokens = DB::table('personal_access_tokens')
            ->join('users', 'personal_access_tokens.tokenable_id', '=', 'users.id')
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->where('personal_access_tokens.tokenable_type', User::class)
            ->whereNotNull('personal_access_tokens.last_used_at')
            ->where('personal_access_tokens.last_used_at', '>=', now()->subHours(24))
            ->select([
                'users.id as user_id',
                'users.name',
                'users.phone',
                'employees.employee_no',
                'personal_access_tokens.name as token_name',
                'personal_access_tokens.last_used_at',
                'personal_access_tokens.created_at as token_created_at',
            ])
            ->orderByDesc('personal_access_tokens.last_used_at')
            ->limit(100)
            ->get()
            ->map(fn ($t) => [
                'user_id' => $t->user_id,
                'name' => $t->name,
                'phone' => $t->phone,
                'employee_no' => $t->employee_no,
                'token_type' => $t->token_name === 'employee-auth-token' ? 'staff' : 'customer',
                'last_active' => $t->last_used_at,
                'session_started' => $t->token_created_at,
            ]);

        return response()->json(['data' => $tokens]);
    }

    /**
     * Verify the 6-digit passcode for sensitive operations.
     */
    private function verifyPasscode(Request $request): void
    {
        $request->validate(['passcode' => ['required', 'string', 'digits:6']]);

        $user = $request->user();

        if (! $user->platform_passcode) {
            abort(422, 'No passcode set. Contact another platform admin.');
        }

        if (! Hash::check($request->passcode, $user->platform_passcode)) {
            activity('platform')
                ->causedBy($user)
                ->event('passcode_failed')
                ->log('Failed passcode verification');

            abort(403, 'Invalid passcode');
        }
    }

    private function generateSimplePassword(): string
    {
        $adjectives = ['Happy', 'Bright', 'Quick', 'Calm', 'Bold', 'Warm', 'Fair', 'Kind', 'Wise', 'Keen'];
        $nouns = ['Star', 'Moon', 'Wave', 'Sun', 'Tree', 'Bird', 'Lake', 'Rock', 'Wind', 'Fire'];
        $specials = ['!', '@', '#', '$', '%'];

        return $adjectives[array_rand($adjectives)]
             .$nouns[array_rand($nouns)]
             .random_int(10, 999)
             .$specials[array_rand($specials)];
    }
}
