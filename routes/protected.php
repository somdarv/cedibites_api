<?php

use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('menu-items/{menuItem}/rate', [MenuItemController::class, 'rate']);

Route::get('kitchen/orders', [OrderController::class, 'kitchenOrders']);
Route::get('order-manager/orders', [OrderController::class, 'orderManagerOrders']);

Route::get('orders', [OrderController::class, 'index']);
Route::get('orders/{order}', [OrderController::class, 'show']);
Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update']);
Route::delete('orders/{order}', [OrderController::class, 'destroy']);
// Customer cancel removed — cancellation is now staff-request + admin-approve only
// Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
Route::post('orders/{order}/refund', [\App\Http\Controllers\Api\PaymentController::class, 'refundOrder'])->name('orders.refund');

Route::get('notifications', [NotificationController::class, 'index']);
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
