<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;

class ChatMessagePolicy
{
    public function send(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->can('chat.participate')
            && ! $user->isBanned()
            && ! $user->isChatBanned();
    }

    public function delete(User $user, ChatMessage $message): bool
    {
        return $user->can('chat.moderate');
    }

    public function ban(User $user, User $target): bool
    {
        return $user->can('chat.moderate');
    }

    public function unban(User $user, User $target): bool
    {
        return $user->can('chat.moderate');
    }
}
