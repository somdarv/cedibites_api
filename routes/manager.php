<?php

use App\Http\Controllers\Api\BranchController;
use Illuminate\Support\Facades\Route;

Route::prefix('manager')->middleware('permission:view_branches')->group(function () {
    Route::get('branches/{branch}/employees', [BranchController::class, 'employees'])
        ->middleware('permission:view_employees');
    Route::get('branches/{branch}/orders', [BranchController::class, 'orders'])
        ->middleware('permission:view_orders');
    Route::get('branches/{branch}/stats', [BranchController::class, 'stats']);
    Route::get('branches/{branch}/top-items', [BranchController::class, 'topItems']);
    Route::get('branches/{branch}/revenue-chart', [BranchController::class, 'revenueChart']);
    Route::get('branches/{branch}/staff-sales', [BranchController::class, 'staffSales']);
});
