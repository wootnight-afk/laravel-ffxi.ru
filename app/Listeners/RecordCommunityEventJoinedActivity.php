<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Events\CommunityEventJoined;
use App\Services\ActivityLogger;

class RecordCommunityEventJoinedActivity
{
    public function __construct(
        private readonly ActivityLogger $activities,
    ) {}

    public function handle(CommunityEventJoined $event): void
    {
        $this->activities->log(
            ActivityType::EventJoined,
            $event->actor,
            $event->event,
            [
                'title' => $event->event->title,
                'starts_at' => $event->event->starts_at->toIso8601String(),
                'participant_name' => $event->actor->name,
            ],
        );
    }
}
