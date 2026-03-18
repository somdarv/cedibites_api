<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminAnalyticsController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\MenuItemSizeController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware('permission:view_orders')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index']);
    });

    Route::middleware('permission:view_employees')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::get('employees/{employee}', [EmployeeController::class, 'show']);
    });

    Route::middleware('permission:manage_employees')->group(function () {
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::post('employees/{employee}/force-logout', [EmployeeController::class, 'forceLogout']);
        Route::post('employees/{employee}/require-password-reset', [EmployeeController::class, 'requirePasswordReset']);

        // Role and permission endpoints for staff management
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('permissions', [RoleController::class, 'permissions']);
    });

    Route::middleware('permission:view_customers')->group(function () {
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::get('customers/{customer}/orders', [CustomerController::class, 'orders']);
    });

    Route::middleware('permission:manage_customers')->group(function () {
        Route::post('customers', [CustomerController::class, 'store']);
        Route::patch('customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
        Route::patch('customers/{customer}/suspend', [CustomerController::class, 'suspend']);
        Route::patch('customers/{customer}/unsuspend', [CustomerController::class, 'unsuspend']);
    });

    Route::middleware('permission:view_branches')->group(function () {
        Route::get('branches', [BranchController::class, 'index']);
        Route::get('branches/basic', [BranchController::class, 'basic']);
        Route::get('branches/{branch}', [BranchController::class, 'show']);
        Route::get('branches/{branch}/employees', [BranchController::class, 'employees']);
        Route::get('branches/{branch}/orders', [BranchController::class, 'orders']);
        Route::get('branches/{branch}/stats', [BranchController::class, 'stats']);
    });

    Route::middleware('permission:manage_branches')->group(function () {
        Route::post('branches', [BranchController::class, 'store']);
        Route::patch('branches/{branch}', [BranchController::class, 'update']);
        Route::delete('branches/{branch}', [BranchController::class, 'destroy']);
        Route::patch('branches/{branch}/toggle-status', [BranchController::class, 'toggleDailyStatus']);
        Route::delete('branches/{branch}/manual-override', [BranchController::class, 'clearManualOverride']);
    });

    Route::middleware('permission:manage_menu')->group(function () {
        Route::apiResource('menu-categories', MenuCategoryController::class);
    });

    Route::middleware('permission:manage_menu')->group(function () {
        Route::post('menu-items', [MenuItemController::class, 'store']);
        Route::post('menu-items/bulk-import-preview', [MenuItemController::class, 'bulkImportPreview']);
        Route::post('menu-items/bulk-import', [MenuItemController::class, 'bulkImport']);
        Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update']);
        Route::post('menu-items/{menuItem}/image', [MenuItemController::class, 'uploadImage']);
        Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy']);

        // Menu item sizes
        Route::get('menu-items/{menuItem}/sizes', [MenuItemSizeController::class, 'index']);
        Route::post('menu-items/{menuItem}/sizes', [MenuItemSizeController::class, 'store']);
        Route::get('menu-items/{menuItem}/sizes/{size}', [MenuItemSizeController::class, 'show']);
        Route::patch('menu-items/{menuItem}/sizes/{size}', [MenuItemSizeController::class, 'update']);
        Route::delete('menu-items/{menuItem}/sizes/{size}', [MenuItemSizeController::class, 'destroy']);
    });

    Route::middleware('permission:view_orders')->group(function () {
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
    });

    Route::middleware('permission:update_orders')->group(function () {
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);
    });

    Route::middleware('permission:view_activity_log')->group(function () {
        Route::get('activity-logs', [ActivityLogController::class, 'index']);
    });

    Route::middleware('permission:view_orders')->group(function () {
        Route::prefix('analytics')->group(function () {
            Route::get('sales', [AdminAnalyticsController::class, 'sales']);
            Route::get('orders', [AdminAnalyticsController::class, 'orders']);
            Route::get('customers', [AdminAnalyticsController::class, 'customers']);
            Route::get('order-sources', [AdminAnalyticsController::class, 'orderSources']);
            Route::get('top-items', [AdminAnalyticsController::class, 'topItems']);
            Route::get('bottom-items', [AdminAnalyticsController::class, 'bottomItems']);
            Route::get('category-revenue', [AdminAnalyticsController::class, 'categoryRevenue']);
            Route::get('branch-performance', [AdminAnalyticsController::class, 'branchPerformance']);
            Route::get('delivery-pickup', [AdminAnalyticsController::class, 'deliveryPickup']);
            Route::get('payment-methods', [AdminAnalyticsController::class, 'paymentMethods']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('daily', [AdminReportController::class, 'daily']);
            Route::get('monthly', [AdminReportController::class, 'monthly']);
        });
    });
});
