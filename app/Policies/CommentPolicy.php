<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Comment $comment): bool
    {
        if ($comment->isSpam()) {
            return $user->can('comments.moderate');
        }

        if ($comment->isApproved()) {
            return true;
        }

        return $comment->user_id === $user->id
            || $user->can('comments.moderate');
    }

    public function create(User $user): bool
    {
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isBanned()) {
            return false;
        }

        return $user->can('comments.create');
    }

    public function update(User $user, Comment $comment): bool
    {
        if ($user->can('comments.moderate')) {
            return true;
        }

        if ($comment->user_id !== $user->id) {
            return false;
        }

        // Автор может редактировать только в течение 5 минут (frontend-spec §6.5)
        return $comment->created_at->diffInMinutes(now()) <= 5;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id
            || $user->can('comments.moderate');
    }

    public function moderate(User $user): bool
    {
        return $user->can('comments.moderate');
    }

    public function report(User $user, Comment $comment): bool
    {
        return $comment->user_id !== $user->id;
    }
}
