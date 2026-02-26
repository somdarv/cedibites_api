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

        foreach ($permissions as $permission) {
            if (! $request->user()->can($permission)) {
                return response()->error('You do not have permission to perform this action.', 403);
            }
        }

        return $next($request);
    }
}
