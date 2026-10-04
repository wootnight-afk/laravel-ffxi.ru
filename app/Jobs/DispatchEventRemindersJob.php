<?php

namespace App\Jobs;

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchEventRemindersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function handle(): void
    {
        $now = now();
        $windowStart = $now->copy()->subMinutes(5)->addHour();
        $windowEnd = $now->copy()->addSeconds(65)->addHour();

        Event::query()
            ->where('status', EventStatus::Planned)
            ->where('starts_at', '>', $now)
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->whereHas('participants', fn ($query) => $query->where('status', EventParticipantStatus::Joined))
            ->orderBy('starts_at')
            ->get(['id', 'starts_at'])
            ->each(fn (Event $event) => SendEventStartingSoonReminderJob::dispatch(
                (int) $event->getKey(),
                $event->starts_at->toIso8601String(),
            ));
    }
}
