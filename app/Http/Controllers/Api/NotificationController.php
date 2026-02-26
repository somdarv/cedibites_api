<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $unreadOnly = $request->boolean('unread_only', false);

        $query = $request->user()->notifications();

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->paginate($perPage);

        return response()->success($notifications);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->success(['count' => $count]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->success($notification);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->success(['message' => 'All notifications marked as read']);
    }

    /**
     * Delete notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->delete();

        return response()->noContent();
    }
}
