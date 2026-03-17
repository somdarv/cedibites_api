<?php

use App\Http\Controllers\Api\MenuConfigController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('orders', [OrderController::class, 'index']);
Route::get('orders/{order}', [OrderController::class, 'show']);
Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update']);
Route::delete('orders/{order}', [OrderController::class, 'destroy']);

Route::get('notifications', [NotificationController::class, 'index']);
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

Route::put('menu-config', [MenuConfigController::class, 'update'])
    ->middleware('permission:manage_menu');
