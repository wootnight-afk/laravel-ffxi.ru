<?php

namespace App\Policies;

use App\Models\Album;
use App\Models\User;

class AlbumPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, Album $album): bool
    {
        if ($album->isSite()) {
            return $album->is_published;
        }

        // Player-альбом: видит владелец или admin; публично — если профиль открыт.
        if ($user !== null && ($user->id === $album->user_id || $user->isAdmin())) {
            return true;
        }

        return $album->is_published && $album->user?->isProfilePublic();
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && ! $user->isBanned()
            && $user->can('albums.create_own');
    }

    public function createSite(User $user): bool
    {
        return $user->can('albums.manage_site');
    }

    public function update(User $user, Album $album): bool
    {
        if ($album->isSite()) {
            return $user->can('albums.manage_site');
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $album->user_id === $user->id && $user->can('albums.edit_own');
    }

    public function delete(User $user, Album $album): bool
    {
        return $this->update($user, $album);
    }
}
