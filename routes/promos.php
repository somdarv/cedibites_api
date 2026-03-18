<?php

use App\Http\Controllers\Api\PromoController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:manage_menu')->group(function () {
    Route::get('promos', [PromoController::class, 'index']);
    Route::get('promos/{promo}', [PromoController::class, 'show']);
    Route::post('promos', [PromoController::class, 'store']);
    Route::patch('promos/{promo}', [PromoController::class, 'update']);
    Route::delete('promos/{promo}', [PromoController::class, 'destroy']);
});
