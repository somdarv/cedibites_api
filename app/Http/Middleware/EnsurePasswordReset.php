<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordReset
{
    /**
     * Block employees who must reset their password from accessing any route
     * except change-password and logout.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->must_reset_password) {
            return response()->json([
                'message' => 'You must reset your password before continuing.',
                'must_reset_password' => true,
            ], 403);
        }

        return $next($request);
    }
}
