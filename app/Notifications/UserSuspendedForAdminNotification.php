<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserSuspendedForAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $reason,
        public readonly string $suspendedAt,
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
            'user_id' => $this->user->getKey(),
            'reason' => $this->reason,
            'suspended_at' => $this->suspendedAt,
        ];
    }
}
