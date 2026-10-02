<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserDeletionRequestedForAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $reason,
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

        // NOTE(stage-8): Add an action link to the Filament UserResource
        // once the admin panel is available. Profil link is intentionally
        // omitted: PlayerController returns 404 for deletion_requested users.
        return (new MailMessage)
            ->subject('[FFXI] Запрос на удаление аккаунта')
            ->greeting('Уведомление администратору')
            ->line('Пользователь запросил удаление своего аккаунта.')
            ->line("Пользователь: {$this->user->name} (ID {$this->user->getKey()})")
            ->line("Причина: {$this->reason}")
            ->line("Когда: {$when}")
            ->line("IP: {$this->ip}")
            ->line("User-Agent: {$this->userAgent}")
            ->line('Аккаунт переведён в статус deletion_requested. Окончательное удаление или восстановление выполняется вручную.')
            ->salutation('— FFXI Phoenix, security notification');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'reason' => $this->reason,
            'ip' => $this->ip,
            'occurred_at' => $this->occurredAt ?? now()->toIso8601String(),
        ];
    }
}
