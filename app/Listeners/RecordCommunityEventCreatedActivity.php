<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Events\CommunityEventCreated;
use App\Services\ActivityLogger;

class RecordCommunityEventCreatedActivity
{
    public function __construct(
        private readonly ActivityLogger $activities,
    ) {}

    public function handle(CommunityEventCreated $event): void
    {
        $event->event->loadMissing('type');
        $this->activities->log(
            ActivityType::EventCreated,
            $event->actor,
            $event->event,
            [
                'title' => $event->event->title,
                'starts_at' => $event->event->starts_at->toIso8601String(),
                'event_type' => $event->event->type?->title,
            ],
        );
    }
}
