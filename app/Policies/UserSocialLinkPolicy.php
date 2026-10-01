<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserSocialLink;

class UserSocialLinkPolicy
{
    public function create(User $user): bool
    {
        return $user->can('profile.edit_own');
    }

    public function update(User $user, UserSocialLink $link): bool
    {
        if ($user->id === $link->user_id) {
            return $user->can('profile.edit_own');
        }

        return $user->can('users.manage');
    }

    public function delete(User $user, UserSocialLink $link): bool
    {
        return $this->update($user, $link);
    }
}
