<?php

namespace App\Events;

use App\Models\User;

final readonly class UserRegisteredForActivity
{
    public function __construct(
        public User $user,
    ) {}
}
