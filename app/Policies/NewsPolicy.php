<?php

namespace App\Policies;

use App\Models\News;
use App\Models\User;

class NewsPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, News $news): bool
    {
        if ($news->isSite()) {
            return $news->isPublished() || $user->can('news.manage_site');
        }

        // Player-news
        if ($user->isAdmin()) {
            return true;
        }

        if ($news->user_id === $user->id) {
            return true; // автор видит свой pending/rejected
        }

        return $news->isPublished();
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->can('news.create_own');
    }

    public function createSite(User $user): bool
    {
        return $user->can('news.manage_site');
    }

    public function update(User $user, News $news): bool
    {
        if ($news->isSite()) {
            return $user->can('news.manage_site');
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($news->user_id === $user->id) {
            return $user->hasVerifiedEmail() && $user->can('news.edit_own');
        }

        return $user->can('news.edit_any');
    }

    public function delete(User $user, News $news): bool
    {
        return $this->update($user, $news);
    }

    public function moderate(User $user, News $news): bool
    {
        if ($news->isSite()) {
            return $user->can('news.manage_site');
        }

        return $user->can('news.moderate');
    }

    public function archive(User $user, News $news): bool
    {
        return $user->isEditor() || $user->isAdmin();
    }
}
