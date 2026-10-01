<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;

class PhotoPolicy
{
    public function view(?User $user, Photo $photo): bool
    {
        if (! $photo->is_published) {
            return $user !== null && ($user->isAdmin() || $user->id === $photo->user_id);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && ! $user->isBanned();
    }

    public function upload(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && ! $user->isBanned()
            && $user->can('photos.upload_own');
    }

    public function update(User $user, Photo $photo): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $photo->user_id === $user->id && $user->can('photos.edit_own');
    }

    public function delete(User $user, Photo $photo): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($photo->user_id === $user->id) {
            return $user->can('photos.edit_own');
        }

        return $user->can('photos.delete_any');
    }
}
