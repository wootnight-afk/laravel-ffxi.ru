<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class EventCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Event $event,
        public readonly User $leader,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => [
                'id' => $this->event->getKey(),
                'title' => $this->event->title,
                'starts_at' => $this->event->starts_at->toIso8601String(),
                'status' => $this->event->status->value,
            ],
            'leader' => [
                'id' => $this->leader->getKey(),
                'name' => $this->leader->name,
            ],
            'reason' => $this->reason,
            'url' => $this->eventUrl($this->event),
        ];
    }

    private function eventUrl(Event $event): string
    {
        return Route::has('events.show')
            ? route('events.show', $event)
            : url("/events/{$event->id}");
    }
}
