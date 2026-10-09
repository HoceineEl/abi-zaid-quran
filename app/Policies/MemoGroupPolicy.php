<?php

namespace App\Policies;

use App\Models\MemoGroup;
use App\Models\User;

class MemoGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MemoGroup $memoGroup): bool
    {
        return $user->isAdministrator() || $memoGroup->teacher_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, MemoGroup $memoGroup): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, MemoGroup $memoGroup): bool
    {
        return $user->isAdministrator();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }
}
