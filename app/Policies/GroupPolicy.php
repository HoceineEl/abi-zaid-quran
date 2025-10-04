<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Group $group): bool
    {
        return $user->isAdministrator();
    }
}
