<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelRequestController extends Controller
{
    /**
     * POST /employee/orders/{order}/request-cancel — staff/manager requests cancellation.
     */
    public function requestCancel(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (! $order->canTransitionTo('cancel_requested')) {
            return response()->json([
                'message' => "Cannot request cancellation for an order with status '{$order->status}'.",
            ], 422);
        }

        $order->update([
            'status' => 'cancel_requested',
            'cancel_requested_by' => $request->user()->id,
            'cancel_request_reason' => $validated['reason'],
            'cancel_requested_at' => now(),
        ]);

        activity('orders')
            ->causedBy($request->user())
            ->performedOn($order)
            ->withProperties([
                'reason' => $validated['reason'],
            ])
            ->event('cancel_requested')
            ->log("Cancel requested for order {$order->order_number}");

        $order->load(['customer.user', 'branch', 'items.menuItem', 'payments', 'statusHistory']);

        return response()->json([
            'message' => 'Cancellation request submitted.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * POST /admin/orders/{order}/approve-cancel — admin approves the cancellation.
     */
    public function approveCancel(Request $request, Order $order): JsonResponse
    {
        if ($order->status !== 'cancel_requested') {
            return response()->json([
                'message' => 'Order does not have a pending cancellation request.',
            ], 422);
        }

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $order->cancel_request_reason,
        ]);

        activity('orders')
            ->causedBy($request->user())
            ->performedOn($order)
            ->event('cancel_approved')
            ->log("Cancellation approved for order {$order->order_number}");

        $order->load(['customer.user', 'branch', 'items.menuItem', 'payments', 'statusHistory']);

        return response()->json([
            'message' => 'Order cancellation approved.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * POST /admin/orders/{order}/reject-cancel — admin rejects the cancellation.
     */
    public function rejectCancel(Request $request, Order $order): JsonResponse
    {
        if ($order->status !== 'cancel_requested') {
            return response()->json([
                'message' => 'Order does not have a pending cancellation request.',
            ], 422);
        }

        // Revert to previous status from history
        $previousStatus = OrderStatusHistory::where('order_id', $order->id)
            ->where('status', '!=', 'cancel_requested')
            ->latest('changed_at')
            ->first();

        $revertTo = $previousStatus?->status ?? 'received';

        $order->update([
            'status' => $revertTo,
            'cancel_requested_by' => null,
            'cancel_request_reason' => null,
            'cancel_requested_at' => null,
        ]);

        activity('orders')
            ->causedBy($request->user())
            ->performedOn($order)
            ->withProperties(['reverted_to' => $revertTo])
            ->event('cancel_rejected')
            ->log("Cancellation rejected for order {$order->order_number}, reverted to {$revertTo}");

        $order->load(['customer.user', 'branch', 'items.menuItem', 'payments', 'statusHistory']);

        return response()->json([
            'message' => 'Cancellation request rejected. Order reverted to previous status.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * POST /admin/orders/{order}/cancel — admin direct cancel (no request needed).
     */
    public function directCancel(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (in_array($order->status, ['cancelled', 'completed', 'delivered'])) {
            return response()->json([
                'message' => "Cannot cancel an order with status '{$order->status}'.",
            ], 422);
        }

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $validated['reason'],
        ]);

        activity('orders')
            ->causedBy($request->user())
            ->performedOn($order)
            ->withProperties(['reason' => $validated['reason']])
            ->event('cancelled')
            ->log("Order {$order->order_number} directly cancelled by admin");

        $order->load(['customer.user', 'branch', 'items.menuItem', 'payments', 'statusHistory']);

        return response()->json([
            'message' => 'Order cancelled.',
            'data' => new OrderResource($order),
        ]);
    }
}
