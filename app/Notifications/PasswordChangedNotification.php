<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
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

        return (new MailMessage)
            ->subject('Пароль изменён')
            ->greeting('Здравствуйте!')
            ->line('Пароль от вашего аккаунта FFXI Phoenix был успешно изменён.')
            ->line("Когда: {$when}")
            ->line("IP: {$this->ip}")
            ->line("User-Agent: {$this->userAgent}")
            ->line('Если это были не вы — немедленно сбросьте пароль и свяжитесь с администрацией.')
            ->action('Сбросить пароль', route('password.request'))
            ->salutation('— FFXI Phoenix');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'occurred_at' => $this->occurredAt ?? now()->toIso8601String(),
        ];
    }
}
