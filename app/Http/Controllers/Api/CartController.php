<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display the authenticated user's active cart.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = Cart::with(['items.menuItem.sizes', 'branch'])
            ->where('customer_id', $request->user()->customer->id)
            ->where('status', 'active')
            ->first();

        if (! $cart) {
            return response()->success(null);
        }

        // Calculate subtotal from items
        $cart->subtotal = $cart->items->sum('subtotal');

        return response()->success($cart);
    }

    /**
     * Add item to cart.
     */
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $cart = Cart::firstOrCreate([
                'customer_id' => $request->user()->customer->id,
                'branch_id' => $validated['branch_id'],
                'status' => 'active',
            ]);

            // Check if item already exists in cart
            $existingItem = CartItem::where('cart_id', $cart->id)
                ->where('menu_item_id', $validated['menu_item_id'])
                ->where('menu_item_size_id', $validated['menu_item_size_id'] ?? null)
                ->first();

            if ($existingItem) {
                // Update existing item quantity
                $newQuantity = $existingItem->quantity + $validated['quantity'];
                $existingItem->update([
                    'quantity' => $newQuantity,
                    'subtotal' => $validated['unit_price'] * $newQuantity,
                ]);
            } else {
                // Create new cart item
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

            // Reload cart with all items and calculate subtotal
            $cart = $cart->fresh(['items.menuItem.sizes', 'branch']);
            $cart->subtotal = $cart->items->sum('subtotal');

            return response()->created($cart);
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Update cart item quantity.
     */
    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        $validated = $request->validated();

        try {
            $cartItem->update([
                'quantity' => $validated['quantity'],
                'subtotal' => $cartItem->unit_price * $validated['quantity'],
            ]);

            // Reload the entire cart with updated totals
            $cart = $cartItem->cart->load(['items.menuItem.sizes', 'branch']);
            $cart->subtotal = $cart->items->sum('subtotal');

            return response()->success($cart);
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Remove item from cart.
     */
    public function destroy(CartItem $cartItem): JsonResponse
    {
        try {
            $cart = $cartItem->cart;
            $cartItem->delete();

            // Return updated cart or null if empty
            $cart = $cart->fresh(['items.menuItem.sizes', 'branch']);
            if ($cart && $cart->items->count() > 0) {
                $cart->subtotal = $cart->items->sum('subtotal');
                return response()->success($cart);
            }

            return response()->success(null);
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Clear entire cart.
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            Cart::where('customer_id', $request->user()->customer->id)
                ->where('status', 'active')
                ->delete();

            return response()->deleted();
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }
}
