<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderFromCartRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\OrderNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    private function taxRate(): float
    {
        return (float) config('app.tax_rate', 0.20);
    }

    /**
     * Resolve cart identity (same logic as CartController).
     *
     * @return array{customer_id: int|null, session_id: string|null}
     */
    private function resolveCartIdentity(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();
        if ($user?->customer) {
            return ['customer_id' => $user->customer->id, 'session_id' => null];
        }

        return ['customer_id' => null, 'session_id' => $request->attributes->get('guest_session_id')];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->customer) {
            return response()->error('Unauthenticated.', 401);
        }

        $orders = Order::with(['customer.user', 'branch', 'items.menuItem'])
            ->where('customer_id', $user->customer->id)
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->paginated(new OrderCollection($orders));
    }

    /**
     * Store a new order from cart (frontend format; auth or guest via cart.identity).
     */
    public function store(StoreOrderFromCartRequest $request): JsonResponse
    {
        $identity = $this->resolveCartIdentity($request);

        $cartQuery = Cart::with(['items.menuItem', 'items.menuItemOption', 'branch'])
            ->where('status', 'active');

        if ($identity['customer_id'] !== null) {
            $cartQuery->where('customer_id', $identity['customer_id']);
        } else {
            $cartQuery->whereNull('customer_id')->where('session_id', $identity['session_id']);
        }

        $cart = $cartQuery->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->error('Cart is empty. Add items before placing an order.', 422);
        }

        $validated = $request->validated();

        if ((int) $validated['branch_id'] !== (int) $cart->branch_id) {
            return response()->error('Cart branch does not match selected branch.', 422);
        }
        $branch = $cart->branch;
        $subtotal = $cart->items->sum('subtotal');
        $deliveryFee = $validated['order_type'] === 'delivery' ? (float) ($branch->delivery_fee ?? 15) : 0;
        $taxAmount = round($subtotal * ($this->taxRate() / (1 + $this->taxRate())), 2);
        $totalAmount = $subtotal + $deliveryFee;

        $orderNumber = (new OrderNumberService)->generate();

        try {
            DB::beginTransaction();

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $identity['customer_id'],
                'branch_id' => (int) $validated['branch_id'],
                'order_type' => $validated['order_type'],
                'order_source' => 'online',
                'delivery_address' => $validated['delivery_address'] ?? null,
                'delivery_latitude' => $validated['delivery_latitude'] ?? null,
                'delivery_longitude' => $validated['delivery_longitude'] ?? null,
                'contact_name' => $validated['customer_name'],
                'contact_phone' => $validated['customer_phone'],
                'delivery_note' => $validated['special_instructions'] ?? null,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'tax_rate' => $this->taxRate(),
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'received',
            ]);

            foreach ($cart->items as $cartItem) {
                $mi = $cartItem->menuItem;
                $opt = $cartItem->menuItemOption;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $cartItem->menu_item_id,
                    'menu_item_option_id' => $cartItem->menu_item_option_id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'subtotal' => $cartItem->subtotal,
                    'special_instructions' => $cartItem->special_instructions,
                    'menu_item_snapshot' => $mi ? [
                        'id' => $mi->id,
                        'name' => $mi->name,
                        'description' => $mi->description,
                    ] : null,
                    'menu_item_option_snapshot' => $opt ? [
                        'id' => $opt->id,
                        'option_key' => $opt->option_key,
                        'option_label' => $opt->option_label,
                        'price' => (float) $opt->price,
                        'image_url' => $opt->getFirstMediaUrl('menu-item-options') ?: null,
                    ] : null,
                ]);
            }

            $paymentMethod = $validated['payment_method'];
            $paymentStatus = $paymentMethod === 'cash' ? 'completed' : 'pending';

            Payment::create([
                'order_id' => $order->id,
                'customer_id' => $identity['customer_id'],
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'amount' => $totalAmount,
                'paid_at' => $paymentStatus === 'completed' ? now() : null,
            ]);

            $cart->update(['status' => 'completed']);

            DB::commit();

            $order->load(['customer.user', 'branch', 'items.menuItem', 'payments']);

            return response()->created(new OrderResource($order));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed', ['error' => $e->getMessage(), 'exception' => $e]);

            return response()->server_error();
        }
    }

    /**
     * Display order by order number (public, for guest tracking).
     */
    public function showByNumber(string $orderNumber): JsonResponse
    {
        $order = Order::with(['customer.user', 'branch', 'items.menuItem', 'items.menuItemOption.media', 'statusHistory', 'payments'])
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            return response()->error('Order not found.', 404);
        }

        return response()->success(new OrderResource($order));
    }

    /**
     * Order manager feed. Authenticated employees see only their branches; optional branch_id must be allowed.
     * Unauthenticated callers may filter by branch_id only (display boards).
     */
    public function orderManagerOrders(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');

        $query = Order::with(['branch', 'customer.user', 'items.menuItem', 'items.menuItemOption.media', 'statusHistory', 'payments'])
            ->whereIn('status', ['received', 'preparing', 'ready'])
            ->orderBy('created_at', 'asc');

        $user = Auth::guard('sanctum')->user();
        $employee = $user?->employee;

        if ($employee && ! $user->hasAnyRole([Role::Admin, Role::SuperAdmin])) {
            $allowed = $employee->branches()->pluck('branches.id');
            $query->whereIn('branch_id', $allowed);
            if ($branchId !== null && $branchId !== '') {
                $bid = (int) $branchId;
                if (! $allowed->contains($bid)) {
                    return response()->json(['message' => 'You cannot view orders for this branch.'], 403);
                }
                $query->where('branch_id', $bid);
            }
        } elseif ($branchId !== null && $branchId !== '') {
            $query->where('branch_id', (int) $branchId);
        }

        return response()->success(OrderResource::collection($query->get()));
    }

    /**
     * Get orders for kitchen display (public, no auth required).
     * Returns orders in kitchen-relevant statuses: received, accepted, preparing, ready.
     */
    public function kitchenOrders(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');

        $query = Order::with(['branch', 'items.menuItem', 'items.menuItemOption.media'])
            ->whereIn('status', ['received', 'accepted', 'preparing', 'ready'])
            ->orderBy('created_at', 'asc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $orders = $query->get();

        return response()->success(OrderResource::collection($orders));
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
            Log::error('Order update failed', ['order_id' => $order->id, 'error' => $e->getMessage(), 'exception' => $e]);

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
            Log::error('Order deletion failed', ['order_id' => $order->id, 'error' => $e->getMessage(), 'exception' => $e]);

            return response()->server_error();
        }
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $terminal = ['delivered', 'completed', 'cancelled'];

        if (in_array($order->status, $terminal)) {
            return response()->error('Order cannot be cancelled in its current status', 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $validated['reason'] ?? null,
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($order)
            ->withProperties(['reason' => $validated['reason'] ?? null])
            ->log("Order {$order->order_number} cancelled");

        return response()->success(
            new OrderResource($order->fresh(['customer.user', 'branch', 'items', 'payments']))
        );
    }
}
