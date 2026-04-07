<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    /**
     * Verify the authenticated user has access to the {branch} in the route.
     * Managers must manage the branch. Partners must be assigned to it.
     * Super admins and admins bypass this check.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Super admins and admins bypass branch ownership checks
        if ($user->hasAnyRole(['tech_admin', 'admin'])) {
            return $next($request);
        }

        $branch = $request->route('branch');

        if (! $branch instanceof Branch) {
            return $next($request);
        }

        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        $hasAccess = $employee->branches()->where('branches.id', $branch->id)->exists();

        if (! $hasAccess) {
            \Log::warning('Branch access denied', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
            ]);

            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        return $next($request);
    }
}
