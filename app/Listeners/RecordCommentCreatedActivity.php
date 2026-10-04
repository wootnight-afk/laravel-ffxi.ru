<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Events\CommentCreatedForActivity;
use App\Models\Event;
use App\Models\News;
use App\Models\Photo;
use App\Services\ActivityLogger;

class RecordCommentCreatedActivity
{
    public function __construct(
        private readonly ActivityLogger $activities,
    ) {}

    public function handle(CommentCreatedForActivity $event): void
    {
        $comment = $event->comment;
        $comment->loadMissing('commentable');
        $subject = $comment->commentable;

        if (! $subject instanceof News && ! $subject instanceof Photo && ! $subject instanceof Event) {
            return;
        }

        $title = match (true) {
            $subject instanceof News => $subject->title,
            $subject instanceof Photo => $subject->caption ?: $subject->album?->title,
            $subject instanceof Event => $subject->title,
        };

        $this->activities->log(
            ActivityType::CommentCreated,
            $event->actor,
            $comment,
            [
                'target_type' => class_basename($subject),
                'target_title' => $title,
            ],
        );
    }
}
