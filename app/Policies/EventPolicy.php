<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->can('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasVerifiedEmail()
            && $event->status === EventStatus::Planned
            && ($event->user_id === $user->id || $this->manageAny($user));
    }

    public function cancel(User $user, Event $event): bool
    {
        return $user->hasVerifiedEmail()
            && $event->status === EventStatus::Planned
            && ($event->user_id === $user->id || $this->manageAny($user));
    }

    public function join(User $user, Event $event): bool
    {
        return $user->hasVerifiedEmail() && $user->can('events.join');
    }

    public function leave(User $user, Event $event): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function manageAny(User $user): bool
    {
        return $user->can('events.manage_any');
    }
}
