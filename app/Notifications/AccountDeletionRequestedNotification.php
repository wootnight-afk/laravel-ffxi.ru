<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $reason,
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

        return (new MailMessage)
            ->subject('Запрос на удаление аккаунта получен')
            ->greeting('Здравствуйте!')
            ->line('Мы получили ваш запрос на удаление аккаунта FFXI Phoenix.')
            ->line("Когда: {$when}")
            ->line("Указанная причина: {$this->reason}")
            ->line('Ваш аккаунт закрыт для входа, публичный профиль недоступен.')
            ->line('Пока это только запрос: автоматического удаления данных не произошло.')
            ->line('Окончательное удаление выполняет администрация. Исторические материалы сообщества могут быть сохранены по её решению.')
            ->line('Если вы передумали — свяжитесь с администрацией.')
            ->action('Связаться с администрацией', route('contacts'))
            ->salutation('— FFXI Phoenix');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt ?? now()->toIso8601String(),
        ];
    }
}
