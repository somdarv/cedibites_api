<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutSessionController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('cart/claim-guest', [CartController::class, 'claimGuest'])->middleware(['auth:sanctum', 'customer.active']);

Route::middleware('cart.identity')->group(function () {
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart/items', [CartController::class, 'store']);
    Route::patch('cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('cart/clear', [CartController::class, 'clear']);
    Route::post('orders', [OrderController::class, 'store']);

    // Checkout sessions (online)
    Route::post('checkout-sessions', [CheckoutSessionController::class, 'store'])
        ->middleware('throttle:5,1');
    Route::get('checkout-sessions/{token}', [CheckoutSessionController::class, 'show']);
    Route::delete('checkout-sessions/{token}', [CheckoutSessionController::class, 'destroy']);
    Route::post('checkout-sessions/{token}/retry-payment', [CheckoutSessionController::class, 'retryPayment']);
    Route::post('checkout-sessions/{token}/change-payment', [CheckoutSessionController::class, 'changePayment']);
});
