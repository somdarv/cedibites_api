<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCartIdentity
{
    /**
     * Allow request if: (a) authenticated user with customer, or (b) valid X-Guest-Session header.
     * Optionally authenticates via Sanctum when Bearer token is present.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();
        if ($user && $user->relationLoaded('customer') === false) {
            $user->load('customer');
        }
        if ($user?->customer) {
            return $next($request);
        }

        $guestSession = $request->header('X-Guest-Session');
        if ($guestSession && $this->isValidGuestSession($guestSession)) {
            $request->attributes->set('guest_session_id', $guestSession);

            return $next($request);
        }

        return response()->error('Cart requires authentication or a valid X-Guest-Session header.', 401);
    }

    private function isValidGuestSession(string $value): bool
    {
        // Accept standard UUID format (v4) or a 16-64 char alphanumeric token
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            return true;
        }

        return strlen($value) >= 16 && strlen($value) <= 64 && preg_match('/^[a-zA-Z0-9\-_]+$/', $value);
    }
}
