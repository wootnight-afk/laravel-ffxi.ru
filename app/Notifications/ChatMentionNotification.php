<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ChatMentionNotification extends Notification
{
    public function __construct(
        public readonly ChatMessage $message,
        public readonly User $sender,
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
            'message_id' => $this->message->getKey(),
            'chat_context' => 'community',
            'sender' => [
                'id' => $this->sender->getKey(),
                'name' => $this->sender->name,
            ],
        ];
    }
}
