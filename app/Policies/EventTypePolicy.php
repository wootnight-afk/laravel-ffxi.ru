<?php

namespace App\Policies;

use App\Models\EventType;
use App\Models\User;

class EventTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, EventType $eventType): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, EventType $eventType): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, EventType $eventType): bool
    {
        return $this->viewAny($user);
    }
}
