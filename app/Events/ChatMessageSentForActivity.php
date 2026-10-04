<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\User;

final readonly class ChatMessageSentForActivity
{
    public function __construct(
        public ChatMessage $message,
        public User $actor,
    ) {}
}
