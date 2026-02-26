<?php

use App\Http\Controllers\Api\AdminAnalyticsController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeOrderController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

// Customer Authentication
Route::prefix('auth')->group(function () {
    Route::post('send-otp', [AuthController::class, 'sendOTP'])->middleware('throttle:otp-send');
    Route::post('verify-otp', [AuthController::class, 'verifyOTP'])->middleware('throttle:otp-verify');
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Employee Authentication
Route::prefix('employee')->group(function () {
    Route::post('login', [EmployeeAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [EmployeeAuthController::class, 'logout']);
    });
});

// Public routes
Route::get('branches', [BranchController::class, 'index']);
Route::get('branches/{branch}', [BranchController::class, 'show']);
Route::get('menu-items', [MenuItemController::class, 'index']);
Route::get('menu-items/{menuItem}', [MenuItemController::class, 'show']);

// Protected routes - Require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Cart Management
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart/items', [CartController::class, 'store']);
    Route::patch('cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('cart/clear', [CartController::class, 'clear']);

    // Orders
    Route::apiResource('orders', OrderController::class);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

    // Employee Routes - Require view_orders permission
    Route::prefix('employee')->middleware('permission:view_orders')->group(function () {
        Route::get('orders', [EmployeeOrderController::class, 'index']);
        Route::get('orders/stats', [EmployeeOrderController::class, 'stats']);
        Route::get('orders/pending', [EmployeeOrderController::class, 'pending']);
        Route::patch('orders/{order}/status', [EmployeeOrderController::class, 'updateStatus'])
            ->middleware('permission:update_orders');
    });

    // Manager Routes - Require view_branches permission
    Route::prefix('manager')->middleware('permission:view_branches')->group(function () {
        // Branch management for managers
        Route::get('branches/{branch}/employees', [BranchController::class, 'employees'])
            ->middleware('permission:view_employees');
        Route::get('branches/{branch}/orders', [BranchController::class, 'orders'])
            ->middleware('permission:view_orders');
        Route::get('branches/{branch}/stats', [BranchController::class, 'stats']);
    });

    // Admin Routes - Permission-based access
    Route::prefix('admin')->group(function () {
        // Employee Management - Requires manage_employees permission
        Route::middleware('permission:view_employees')->group(function () {
            Route::get('employees', [EmployeeController::class, 'index']);
            Route::get('employees/{employee}', [EmployeeController::class, 'show']);
        });

        Route::middleware('permission:manage_employees')->group(function () {
            Route::post('employees', [EmployeeController::class, 'store']);
            Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
            Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
        });

        // Customer Management - Requires manage_customers permission
        Route::middleware('permission:view_customers')->group(function () {
            Route::get('customers', [CustomerController::class, 'index']);
            Route::get('customers/{customer}', [CustomerController::class, 'show']);
            Route::get('customers/{customer}/orders', [CustomerController::class, 'orders']);
        });

        Route::middleware('permission:manage_customers')->group(function () {
            Route::post('customers', [CustomerController::class, 'store']);
            Route::patch('customers/{customer}', [CustomerController::class, 'update']);
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
        });

        // Branch Management - Requires manage_branches permission
        Route::middleware('permission:view_branches')->group(function () {
            Route::get('branches/{branch}/employees', [BranchController::class, 'employees']);
            Route::get('branches/{branch}/orders', [BranchController::class, 'orders']);
            Route::get('branches/{branch}/stats', [BranchController::class, 'stats']);
        });

        Route::middleware('permission:manage_branches')->group(function () {
            Route::post('branches', [BranchController::class, 'store']);
            Route::patch('branches/{branch}', [BranchController::class, 'update']);
            Route::delete('branches/{branch}', [BranchController::class, 'destroy']);
        });

        // Menu Category Management - Requires manage_menu permission
        Route::middleware('permission:manage_menu')->group(function () {
            Route::apiResource('menu-categories', MenuCategoryController::class);
        });

        // Menu Item Management - Requires manage_menu permission
        Route::middleware('permission:manage_menu')->group(function () {
            Route::post('menu-items', [MenuItemController::class, 'store']);
            Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update']);
            Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy']);
        });

        // Payment Management - Requires view_orders permission
        Route::middleware('permission:view_orders')->group(function () {
            Route::get('payments', [PaymentController::class, 'index']);
            Route::get('payments/{payment}', [PaymentController::class, 'show']);
        });

        Route::middleware('permission:update_orders')->group(function () {
            Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);
        });

        // Analytics - Requires view_orders permission
        Route::middleware('permission:view_orders')->group(function () {
            Route::prefix('analytics')->group(function () {
                Route::get('sales', [AdminAnalyticsController::class, 'sales']);
                Route::get('orders', [AdminAnalyticsController::class, 'orders']);
                Route::get('customers', [AdminAnalyticsController::class, 'customers']);
            });

            // Reports
            Route::prefix('reports')->group(function () {
                Route::get('daily', [AdminReportController::class, 'daily']);
                Route::get('monthly', [AdminReportController::class, 'monthly']);
            });
        });
    });
});
