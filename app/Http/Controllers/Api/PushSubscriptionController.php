<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Store or update a push subscription for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? 'aesgcm',
        );

        return response()->json(['message' => 'Push subscription saved.'], 201);
    }

    /**
     * Remove a push subscription.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Push subscription removed.']);
    }

    /**
     * Return the VAPID public key so the frontend can subscribe.
     */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'data' => [
                'public_key' => config('webpush.vapid.public_key'),
            ],
        ]);
    }
}
