<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class ChatUnbannedNotification extends Notification
{
    public function __construct(
        public readonly User $moderator,
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
            'moderator' => [
                'id' => $this->moderator->getKey(),
                'name' => $this->moderator->name,
            ],
        ];
    }
}
