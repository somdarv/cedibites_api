<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimGuestCartRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Resolve cart identity: customer_id (authenticated) or session_id (guest).
     *
     * @return array{customer_id: int|null, session_id: string|null}
     */
    private function resolveCartIdentity(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();
        if ($user?->customer) {
            return ['customer_id' => $user->customer->id, 'session_id' => null];
        }

        $guestSession = $request->attributes->get('guest_session_id');

        return ['customer_id' => null, 'session_id' => $guestSession];
    }

    /**
     * Build cart query for the current identity.
     */
    private function cartQuery(Request $request)
    {
        $identity = $this->resolveCartIdentity($request);

        $query = Cart::with(['items.menuItem.sizes', 'items.menuItem.category', 'items.menuItemSize', 'branch'])
            ->where('status', 'active');

        if ($identity['customer_id'] !== null) {
            $query->where('customer_id', $identity['customer_id']);
        } else {
            $query->whereNull('customer_id')->where('session_id', $identity['session_id']);
        }

        return $query;
    }

    /**
     * Check if the given cart belongs to the current identity.
     */
    private function cartBelongsToIdentity(Request $request, Cart $cart): bool
    {
        $identity = $this->resolveCartIdentity($request);

        if ($identity['customer_id'] !== null) {
            return $cart->customer_id === $identity['customer_id'];
        }

        return $cart->session_id === $identity['session_id'] && $cart->customer_id === null;
    }

    /**
     * Display the current user's or guest's active cart.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartQuery($request)->first();

        if (! $cart) {
            return response()->success(null);
        }

        $cart->subtotal = $cart->items->sum('subtotal');

        $response = response()->success(new CartResource($cart));

        if ($cart->session_id && ! $request->header('X-Guest-Session')) {
            $response->header('X-Guest-Session', $cart->session_id);
        }

        return $response;
    }

    /**
     * Add item to cart.
     */
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identity = $this->resolveCartIdentity($request);

        try {
            if ($identity['customer_id'] !== null) {
                $cart = Cart::firstOrCreate(
                    [
                        'customer_id' => $identity['customer_id'],
                        'branch_id' => $validated['branch_id'],
                        'status' => 'active',
                    ],
                    ['session_id' => null]
                );
            } else {
                $cart = Cart::firstOrCreate(
                    [
                        'session_id' => $identity['session_id'],
                        'branch_id' => $validated['branch_id'],
                        'status' => 'active',
                    ],
                    ['customer_id' => null]
                );
            }

            $existingItem = CartItem::where('cart_id', $cart->id)
                ->where('menu_item_id', $validated['menu_item_id'])
                ->where('menu_item_size_id', $validated['menu_item_size_id'] ?? null)
                ->first();

            if ($existingItem) {
                $newQuantity = $existingItem->quantity + $validated['quantity'];
                $existingItem->update([
                    'quantity' => $newQuantity,
                    'subtotal' => $validated['unit_price'] * $newQuantity,
                ]);
            } else {
                $subtotal = $validated['unit_price'] * $validated['quantity'];

                CartItem::create([
                    'cart_id' => $cart->id,
                    'menu_item_id' => $validated['menu_item_id'],
                    'menu_item_size_id' => $validated['menu_item_size_id'] ?? null,
                    'quantity' => $validated['quantity'],
                    'unit_price' => $validated['unit_price'],
                    'subtotal' => $subtotal,
                    'special_instructions' => $validated['special_instructions'] ?? null,
                ]);
            }

            $cart = $cart->fresh(['items.menuItem.sizes', 'items.menuItem.category', 'items.menuItemSize', 'branch']);
            $cart->subtotal = $cart->items->sum('subtotal');

            $response = response()->created(new CartResource($cart));

            if ($cart->session_id) {
                $response->header('X-Guest-Session', $cart->session_id);
            }

            return $response;
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Update cart item quantity.
     */
    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        if (! $this->cartBelongsToIdentity($request, $cartItem->cart)) {
            return response()->error('You do not have permission to update this cart item.', 403);
        }

        $validated = $request->validated();

        try {
            $cartItem->update([
                'quantity' => $validated['quantity'],
                'subtotal' => $cartItem->unit_price * $validated['quantity'],
            ]);

            $cart = $cartItem->cart->load(['items.menuItem.sizes', 'items.menuItem.category', 'items.menuItemSize', 'branch']);
            $cart->subtotal = $cart->items->sum('subtotal');

            return response()->success(new CartResource($cart));
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Remove item from cart.
     */
    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        if (! $this->cartBelongsToIdentity($request, $cartItem->cart)) {
            return response()->error('You do not have permission to remove this cart item.', 403);
        }

        try {
            $cart = $cartItem->cart;
            $cartItem->delete();

            $cart = $cart->fresh(['items.menuItem.sizes', 'items.menuItem.category', 'items.menuItemSize', 'branch']);
            if ($cart && $cart->items->count() > 0) {
                $cart->subtotal = $cart->items->sum('subtotal');

                return response()->success(new CartResource($cart));
            }

            return response()->success(null);
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Claim guest cart(s) for the authenticated customer.
     */
    public function claimGuest(ClaimGuestCartRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->customer) {
            return response()->error('Customer record required.', 403);
        }

        $guestSessionId = $request->validated('guest_session_id');
        $customerId = $user->customer->id;

        try {
            DB::beginTransaction();

            $guestCarts = Cart::with(['items.menuItem.sizes', 'items.menuItem.category', 'items.menuItemSize'])
                ->where('session_id', $guestSessionId)
                ->whereNull('customer_id')
                ->where('status', 'active')
                ->get();

            foreach ($guestCarts as $guestCart) {
                $customerCart = Cart::where('customer_id', $customerId)
                    ->where('branch_id', $guestCart->branch_id)
                    ->where('status', 'active')
                    ->first();

                if (! $customerCart) {
                    $guestCart->update([
                        'customer_id' => $customerId,
                        'session_id' => null,
                    ]);
                } else {
                    foreach ($guestCart->items as $guestItem) {
                        $existing = CartItem::where('cart_id', $customerCart->id)
                            ->where('menu_item_id', $guestItem->menu_item_id)
                            ->where('menu_item_size_id', $guestItem->menu_item_size_id)
                            ->first();

                        if ($existing) {
                            $newQuantity = $existing->quantity + $guestItem->quantity;
                            $existing->update([
                                'quantity' => $newQuantity,
                                'subtotal' => $existing->unit_price * $newQuantity,
                            ]);
                        } else {
                            CartItem::create([
                                'cart_id' => $customerCart->id,
                                'menu_item_id' => $guestItem->menu_item_id,
                                'menu_item_size_id' => $guestItem->menu_item_size_id,
                                'quantity' => $guestItem->quantity,
                                'unit_price' => $guestItem->unit_price,
                                'subtotal' => $guestItem->subtotal,
                                'special_instructions' => $guestItem->special_instructions,
                            ]);
                        }
                    }
                    $guestCart->delete();
                }
            }

            DB::commit();

            if ($guestCarts->isNotEmpty()) {
                activity('cart')
                    ->causedBy($user)
                    ->withProperties([
                        'guest_session_id' => $guestSessionId,
                        'carts_claimed' => $guestCarts->count(),
                        'items_merged' => $guestCarts->sum(fn ($c) => $c->items->count()),
                    ])
                    ->log('Guest cart claimed');
            }

            $cart = Cart::with(['items.menuItem.sizes', 'items.menuItem.category', 'items.menuItemSize', 'branch'])
                ->where('customer_id', $customerId)
                ->where('status', 'active')
                ->first();

            if ($cart) {
                $cart->subtotal = $cart->items->sum('subtotal');

                return response()->success(new CartResource($cart));
            }

            return response()->success(null);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->server_error();
        }
    }

    /**
     * Clear entire cart.
     */
    public function clear(Request $request): JsonResponse|Response
    {
        $identity = $this->resolveCartIdentity($request);

        try {
            $query = Cart::where('status', 'active');

            if ($identity['customer_id'] !== null) {
                $query->where('customer_id', $identity['customer_id']);
            } else {
                $query->whereNull('customer_id')->where('session_id', $identity['session_id']);
            }

            $query->delete();

            return response()->deleted();
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }
}
