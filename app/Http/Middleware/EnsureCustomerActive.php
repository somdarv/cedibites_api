<?php

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerActive
{
    /**
     * Reject requests from suspended customers.
     * Employees and guests pass through — this only guards registered customers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $customer = $user->customer;

        if ($customer && ! $customer->is_guest && $customer->status === CustomerStatus::Suspended) {
            return response()->json([
                'message' => 'Your account has been suspended. Please contact support.',
                'error' => 'account_suspended',
            ], 403);
        }

        return $next($request);
    }
}
