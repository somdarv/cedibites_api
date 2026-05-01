<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderNoteController extends Controller
{
    /**
     * POST /admin/orders/{order}/notes — append an internal note.
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $existing = $order->internal_notes ?? [];

        $entry = [
            'id' => (string) Str::uuid(),
            'note' => $validated['note'],
            'by_user_id' => $request->user()->id,
            'by_name' => $request->user()->name,
            'at' => now()->toIso8601String(),
        ];

        $order->update([
            'internal_notes' => array_merge($existing, [$entry]),
        ]);

        activity('orders')
            ->causedBy($request->user())
            ->performedOn($order)
            ->withProperties(['note_id' => $entry['id']])
            ->event('note_added')
            ->log("Internal note added to order {$order->order_number}");

        $order->load([
            'customer.user',
            'branch',
            'items.menuItem.category',
            'items.menuItemOption.media',
            'payments',
            'statusHistory.changedBy',
            'assignedEmployee.user',
            'cancelRequestedBy',
        ]);

        return response()->json([
            'message' => 'Note saved.',
            'data' => new OrderResource($order),
        ]);
    }
}
