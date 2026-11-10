<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use LocalizesMail;
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
        $locale = $this->mailLocale($notifiable);

        return (new MailMessage)
            ->subject($this->mailLine('password_changed.subject', [], $locale))
            ->greeting($this->mailLine('password_changed.greeting', [], $locale))
            ->line($this->mailLine('password_changed.intro', [], $locale))
            ->line($this->mailLine('fields.when', ['value' => $when], $locale))
            ->line($this->mailLine('fields.ip', ['value' => $this->ip], $locale))
            ->line($this->mailLine('fields.user_agent', ['value' => $this->userAgent], $locale))
            ->line($this->mailLine('password_changed.warning', [], $locale))
            ->action($this->mailLine('password_changed.action', [], $locale), route('password.request'))
            ->salutation($this->mailLine('salutation', [], $locale));
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
