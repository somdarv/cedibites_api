<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PromoController;
use Illuminate\Support\Facades\Route;

Route::prefix('employee')->group(function () {
    Route::post('login', [EmployeeAuthController::class, 'login']);
    Route::post('pos-login', [EmployeeAuthController::class, 'posLogin']);
});

Route::get('branches', [BranchController::class, 'index']);
Route::get('branches/by-name/{name}', [BranchController::class, 'getByName']);
Route::get('branches/{branch}', [BranchController::class, 'show']);
Route::get('branches/{branch}/menu-items', [BranchController::class, 'getMenuItemIds']);
Route::get('branches/{branch}/menu-items/{itemId}/available', [BranchController::class, 'isItemAvailable']);
Route::get('menu-categories', [MenuCategoryController::class, 'index']);
Route::get('menu-items', [MenuItemController::class, 'index']);
Route::get('menu-items/{menuItem}', [MenuItemController::class, 'show']);
Route::get('orders/by-number/{orderNumber}', [OrderController::class, 'showByNumber']);
Route::post('promos/resolve', [PromoController::class, 'resolve']);
