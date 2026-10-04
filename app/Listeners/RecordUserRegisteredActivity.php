<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Events\UserRegisteredForActivity;
use App\Services\ActivityLogger;

class RecordUserRegisteredActivity
{
    public function __construct(
        private readonly ActivityLogger $activities,
    ) {}

    public function handle(UserRegisteredForActivity $event): void
    {
        $this->activities->log(
            ActivityType::Registered,
            $event->user,
            $event->user,
            ['name' => $event->user->name],
        );
    }
}
