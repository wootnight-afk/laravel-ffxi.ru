<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserRank;

class UserRankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ranks.manage');
    }

    public function view(User $user, UserRank $userRank): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, UserRank $userRank): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, UserRank $userRank): bool
    {
        return $this->viewAny($user);
    }
}
