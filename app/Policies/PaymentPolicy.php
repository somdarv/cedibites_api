<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewOrders->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Users with permission can view all payments
        if ($user->can(Permission::ViewOrders->value)) {
            return true;
        }

        // Customers can only view their own payments
        return $user->customer && $payment->order->customer_id === $user->customer->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::CreateOrders->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $user->can(Permission::UpdateOrders->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->can(Permission::DeleteOrders->value);
    }
}
