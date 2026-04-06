<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('employee')->middleware('throttle:5,1')->group(function () {
    Route::post('forgot-password', [EmployeeAuthController::class, 'forgotPassword']);
    Route::post('reset-password', [EmployeeAuthController::class, 'resetPassword']);
});

Route::prefix('auth')->group(function () {
    Route::post('send-otp', [AuthController::class, 'sendOTP'])->middleware('throttle:otp-send');
    Route::post('verify-otp', [AuthController::class, 'verifyOTP'])->middleware('throttle:otp-verify');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('quick-register', [AuthController::class, 'quickRegister'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->middleware('customer.active')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});
