<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GroupPolicy
{


    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        return auth()->user()->role === 'admin';
    }



    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Group $group): bool
    {
        return auth()->user()->role === 'admin';
    }
}
