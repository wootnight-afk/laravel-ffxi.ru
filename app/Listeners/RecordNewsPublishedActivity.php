<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Events\NewsPublishedForActivity;
use App\Services\ActivityLogger;

class RecordNewsPublishedActivity
{
    public function __construct(
        private readonly ActivityLogger $activities,
    ) {}

    public function handle(NewsPublishedForActivity $event): void
    {
        $this->activities->log(
            ActivityType::NewsPublished,
            $event->actor,
            $event->news,
            [
                'title' => $event->news->title,
                'scope' => $event->news->scope,
            ],
        );
    }
}
