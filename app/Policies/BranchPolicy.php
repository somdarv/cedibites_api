<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Public access to view branches
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Branch $branch): bool
    {
        // Public access to view individual branches
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ManageBranches->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ManageBranches->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ManageBranches->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ManageBranches->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ManageBranches->value);
    }
}
