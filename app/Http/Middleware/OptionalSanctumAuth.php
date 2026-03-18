<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OptionalSanctumAuth
{
    /**
     * Attempt Sanctum authentication without failing. Sets user on request when token is valid.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::guard('sanctum')->user();

        return $next($request);
    }
}
