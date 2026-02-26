<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCustomers->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        // Users with permission can view all customers
        if ($user->can(Permission::ViewCustomers->value)) {
            return true;
        }

        // Users can view their own customer profile
        return $user->customer && $customer->id === $user->customer->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCustomers->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        // Users with permission can update any customer
        if ($user->can(Permission::ManageCustomers->value)) {
            return true;
        }

        // Users can update their own customer profile
        return $user->customer && $customer->id === $user->customer->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::ManageCustomers->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $user->can(Permission::ManageCustomers->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::ManageCustomers->value);
    }
}
