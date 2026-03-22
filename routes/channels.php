<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('orders.branch.{branchId}', function ($user, $branchId) {
    if ($user->hasAnyRole(['admin', 'super_admin'])) {
        return true;
    }

    return $user->employee?->branches()->where('branches.id', $branchId)->exists() ?? false;
});
