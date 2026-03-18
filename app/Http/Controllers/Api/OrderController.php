<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderFromCartRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private const TAX_RATE = 0.025;

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

        $cartQuery = Cart::with(['items.menuItem', 'items.menuItemSize', 'branch'])
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
        $taxAmount = round($subtotal * self::TAX_RATE, 2);
        $totalAmount = $subtotal + $deliveryFee + $taxAmount;

        $orderNumber = 'CB'.substr((string) (time() % 1000000 + 100000), -6);
        while (Order::where('order_number', $orderNumber)->exists()) {
            $orderNumber = 'CB'.fake()->numerify('######');
        }

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
                'tax_rate' => self::TAX_RATE,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'received',
            ]);

            foreach ($cart->items as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $cartItem->menu_item_id,
                    'menu_item_size_id' => $cartItem->menu_item_size_id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'subtotal' => $cartItem->subtotal,
                    'special_instructions' => $cartItem->special_instructions,
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

            return response()->server_error();
        }
    }

    /**
     * Display order by order number (public, for guest tracking).
     */
    public function showByNumber(string $orderNumber): JsonResponse
    {
        $order = Order::with(['customer.user', 'branch', 'items.menuItem', 'items.menuItemSize', 'statusHistory', 'payments'])
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            return response()->error('Order not found.', 404);
        }

        return response()->success(new OrderResource($order));
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
