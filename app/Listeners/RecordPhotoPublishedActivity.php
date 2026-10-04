<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Events\PhotoPublishedForActivity;
use App\Models\Activity;
use App\Models\Photo;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class RecordPhotoPublishedActivity
{
    public function __construct(
        private readonly ActivityLogger $activities,
    ) {}

    public function handle(PhotoPublishedForActivity $event): void
    {
        DB::transaction(function () use ($event): void {
            $photo = Photo::query()
                ->whereKey($event->photo->getKey())
                ->lockForUpdate()
                ->first();

            if ($photo === null) {
                return;
            }

            $alreadyRecorded = Activity::query()
                ->where('type', ActivityType::PhotoPublished->value)
                ->where('subject_type', $photo->getMorphClass())
                ->where('subject_id', $photo->getKey())
                ->exists();

            if ($alreadyRecorded) {
                return;
            }

            // Known limitation: repeated processing of the same uploaded file
            // creates a new Photo record, therefore a new activity. The listener
            // is idempotent only for a given photo_id.
            $photo->loadMissing('album');
            $this->activities->log(
                ActivityType::PhotoPublished,
                $event->actor,
                $photo,
                [
                    'caption' => $photo->caption,
                    'album_title' => $photo->album?->title,
                ],
            );
        });
    }
}
