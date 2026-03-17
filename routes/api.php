<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/public.php';
require __DIR__.'/cart.php';

Route::middleware('auth:sanctum')->group(function () {
    require __DIR__.'/protected.php';
    require __DIR__.'/employee.php';
    require __DIR__.'/manager.php';
    require __DIR__.'/admin.php';
    require __DIR__.'/promos.php';
});

// Hubtel Payment Routes
Route::post('payments/hubtel/callback', [App\Http\Controllers\Api\PaymentController::class, 'hubtelCallback'])
    ->name('payments.hubtel.callback');

Route::middleware('optional.auth')->group(function () {
    Route::post('orders/{order}/payments/hubtel/initiate', [App\Http\Controllers\Api\PaymentController::class, 'initiateHubtelPayment'])
        ->name('payments.hubtel.initiate');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('payments/{payment}/verify', [App\Http\Controllers\Api\PaymentController::class, 'verifyPayment'])
        ->name('payments.verify');
});
