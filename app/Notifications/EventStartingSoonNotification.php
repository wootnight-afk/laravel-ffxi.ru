<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Notifications\Notification;

class EventStartingSoonNotification extends Notification
{
    public function __construct(
        public readonly Event $event,
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
        $startsAt = $this->event->starts_at->toIso8601String();

        return [
            'event_id' => $this->event->getKey(),
            'title' => $this->event->title,
            'starts_at' => $startsAt,
            'event_starts_at' => $startsAt,
            'url' => route('events.show', $this->event),
        ];
    }
}
