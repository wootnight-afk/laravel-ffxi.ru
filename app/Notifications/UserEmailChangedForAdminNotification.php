<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserEmailChangedForAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $oldEmail,
        public readonly string $newEmail,
        public readonly string $ip,
        public readonly string $userAgent,
        public readonly ?string $occurredAt = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->occurredAt ?? now()->toDateTimeString();
        $profileUrl = route('players.show', ['user' => $this->user->name]);

        return (new MailMessage)
            ->subject('[FFXI] Смена email у пользователя')
            ->greeting('Уведомление администратору')
            ->line('Пользователь изменил email своего аккаунта.')
            ->line("Пользователь: {$this->user->name} (ID {$this->user->getKey()})")
            ->line("Было: {$this->oldEmail}")
            ->line("Стало: {$this->newEmail}")
            ->line("Когда: {$when}")
            ->line("IP: {$this->ip}")
            ->line("User-Agent: {$this->userAgent}")
            ->action('Открыть профиль', $profileUrl)
            ->salutation('— FFXI Phoenix, security notification');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'old_email' => $this->oldEmail,
            'new_email' => $this->newEmail,
            'ip' => $this->ip,
            'occurred_at' => $this->occurredAt ?? now()->toIso8601String(),
        ];
    }
}
