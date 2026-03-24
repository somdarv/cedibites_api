<?php

use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeOrderController;
use App\Http\Controllers\Api\PosOrderController;
use App\Http\Controllers\Api\ShiftController;
use Illuminate\Support\Facades\Route;

Route::prefix('employee')->group(function () {
    Route::get('me', [EmployeeAuthController::class, 'me']);
    Route::post('logout', [EmployeeAuthController::class, 'logout']);
    Route::post('change-password', [EmployeeAuthController::class, 'changePassword']);
});

Route::prefix('pos')->group(function () {
    Route::post('orders', [PosOrderController::class, 'store']);
    Route::post('verify-momo', [PosOrderController::class, 'verifyMomo']);
});

Route::prefix('shifts')->group(function () {
    Route::get('/', [ShiftController::class, 'index']);
    Route::get('active/{employeeId}', [ShiftController::class, 'getActive']);
    Route::post('/', [ShiftController::class, 'startShift']);
    Route::get('by-date/{date}', [ShiftController::class, 'getByDate']);
    Route::get('by-staff/{staffId}', [ShiftController::class, 'getByStaff']);
    Route::patch('{shift}/end', [ShiftController::class, 'endShift']);
    Route::post('{shift}/orders', [ShiftController::class, 'addOrder']);
});

Route::prefix('employee')->middleware('permission:view_orders')->group(function () {
    Route::get('orders', [EmployeeOrderController::class, 'index']);
    Route::get('orders/stats', [EmployeeOrderController::class, 'stats']);
    Route::get('orders/pending', [EmployeeOrderController::class, 'pending']);
    Route::patch('orders/{order}/status', [EmployeeOrderController::class, 'updateStatus'])
        ->middleware('permission:update_orders');
});
