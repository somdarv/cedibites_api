<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['customer.user', 'branch', 'items.menuItem'])
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->customer_id, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->paginated(new OrderCollection($orders));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = Order::create($request->validated());

            return response()->created(
                new OrderResource($order->load(['customer.user', 'branch', 'items']))
            );
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['customer.user', 'branch', 'items.menuItem', 'statusHistory', 'payments']);

        return response()->success(new OrderResource($order));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        try {
            $order->update($request->validated());

            return response()->success(
                new OrderResource($order->fresh(['customer.user', 'branch', 'items']))
            );
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            $order->delete();

            return response()->deleted();
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }
}
