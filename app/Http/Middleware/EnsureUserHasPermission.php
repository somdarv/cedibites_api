<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $request->user()) {
            return response()->error('Unauthenticated.', 401);
        }

        $user = $request->user();
        $userRoles = $user->getRoleNames()->toArray();
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                \Log::warning('Permission Denied', [
                    'user_id' => $user->id,
                    'required_permission' => $permission,
                    'user_permissions' => $userPermissions,
                ]);

                return response()->error('You do not have permission to perform this action.', 403);
            }
        }

        return $next($request);
    }
}
