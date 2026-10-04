<?php

namespace App\Events;

use App\Models\Event;
use App\Models\User;

final readonly class CommunityEventCreated
{
    public function __construct(
        public Event $event,
        public User $actor,
    ) {}
}
