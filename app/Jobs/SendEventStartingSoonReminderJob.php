<?php

namespace App\Jobs;

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Notifications\EventStartingSoonNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendEventStartingSoonReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $eventId,
        public readonly string $eventStartsAt,
    ) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $event = Event::query()
                ->whereKey($this->eventId)
                ->lockForUpdate()
                ->first();

            if (
                $event === null
                || $event->status !== EventStatus::Planned
                || $event->starts_at->lessThanOrEqualTo(now())
                || $event->starts_at->toIso8601String() !== $this->eventStartsAt
            ) {
                return;
            }

            $participants = $event->participants()
                ->where('status', EventParticipantStatus::Joined)
                ->with('user')
                ->get();

            foreach ($participants as $participant) {
                $user = $participant->user;
                if (! $user instanceof User) {
                    continue;
                }

                $alreadyNotified = $user->notifications()
                    ->where('type', EventStartingSoonNotification::class)
                    ->where('data->event_id', $event->getKey())
                    ->where('data->event_starts_at', $this->eventStartsAt)
                    ->exists();

                if (! $alreadyNotified) {
                    $user->notify(new EventStartingSoonNotification($event));
                }
            }
        });
    }
}
