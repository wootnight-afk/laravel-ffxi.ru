<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Events\ChatMessageSentForActivity;
use App\Models\Activity;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class RecordChatMessageActivity
{
    public function __construct(
        private readonly ActivityLogger $activities,
    ) {}

    public function handle(ChatMessageSentForActivity $event): void
    {
        DB::transaction(function () use ($event): void {
            $message = $event->message->newQuery()
                ->whereKey($event->message->getKey())
                ->lockForUpdate()
                ->first();

            if ($message === null) {
                return;
            }

            $alreadyRecorded = Activity::query()
                ->where('type', ActivityType::ChatMessage->value)
                ->where('subject_type', $message->getMorphClass())
                ->where('subject_id', $message->getKey())
                ->exists();

            if ($alreadyRecorded) {
                return;
            }

            $this->activities->log(
                ActivityType::ChatMessage,
                $event->actor,
                $message,
                ['chat_context' => 'community'],
            );
        });
    }
}
