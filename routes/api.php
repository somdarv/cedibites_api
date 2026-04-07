<?php

use Illuminate\Support\Facades\Route;

// TEMPORARY DIAGNOSTIC — remove after debugging
Route::get('_debug/deploy-check', function () {
    return response()->json([
        'commit' => trim(shell_exec('git log --oneline -1 2>/dev/null') ?? 'unknown'),
        'dir' => base_path(),
        'php' => PHP_VERSION,
        'routes_cached' => file_exists(base_path('bootstrap/cache/routes-v7.php')),
        'employee_routes_exist' => file_exists(base_path('routes/employee.php')),
        'checkout_controller_exists' => class_exists(\App\Http\Controllers\Api\CheckoutSessionController::class),
        'pos_checkout_registered' => collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())->contains(fn($r) => str_contains($r->uri(), 'pos/checkout-sessions')),
    ]);
});


require __DIR__.'/auth.php';
require __DIR__.'/public.php';
require __DIR__.'/cart.php';

Route::middleware('auth:sanctum')->group(function () {
    require __DIR__.'/protected.php';
    require __DIR__.'/employee.php';
    require __DIR__.'/manager.php';
    require __DIR__.'/admin.php';
    require __DIR__.'/promos.php';
    require __DIR__.'/platform.php';
});

// Hubtel Payment Routes
Route::post('payments/hubtel/callback', [App\Http\Controllers\Api\PaymentController::class, 'hubtelCallback'])
    ->name('payments.hubtel.callback');

// Hubtel Direct Receive Money (RMP) callback â€” used for POS mobile money payments
Route::post('payments/hubtel/rmp/callback', [App\Http\Controllers\Api\PaymentController::class, 'hubtelRmpCallback'])
    ->name('payments.hubtel.rmp.callback');

Route::middleware('optional.auth')->group(function () {
    Route::post('orders/{order}/payments/hubtel/initiate', [App\Http\Controllers\Api\PaymentController::class, 'initiateHubtelPayment'])
        ->name('payments.hubtel.initiate');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('payments/{payment}/verify', [App\Http\Controllers\Api\PaymentController::class, 'verifyPayment'])
        ->name('payments.verify');
});
