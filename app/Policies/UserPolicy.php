<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function view(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $target->isProfilePublic();
    }

    public function update(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return $user->can('profile.edit_own');
        }

        return $user->can('users.manage');
    }

    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false; // Только admin удаляет аккаунты (§6.12)
        }

        return $user->can('users.manage');
    }

    public function ban(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false; // Нельзя банить себя
        }

        if ($target->isAdmin()) {
            return false; // Admin не может забанить admin
        }

        return $user->isAdmin();
    }

    public function assignRank(User $user, User $target): bool
    {
        return $user->can('ranks.manage');
    }
}
