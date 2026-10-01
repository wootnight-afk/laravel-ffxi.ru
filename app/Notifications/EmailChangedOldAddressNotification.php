<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangedOldAddressNotification extends Notification
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
        $maskedNew = $this->mask($this->newEmail);

        return (new MailMessage)
            ->subject('Email аккаунта изменён')
            ->greeting("Здравствуйте, {$this->user->name}!")
            ->line('Email, привязанный к вашему аккаунту FFXI Phoenix, был изменён.')
            ->line("Было: {$this->oldEmail}")
            ->line("Стало: {$maskedNew}")
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
            'user_id' => $this->user->getKey(),
            'old_email' => $this->oldEmail,
            'new_email' => $this->newEmail,
            'ip' => $this->ip,
            'occurred_at' => $this->occurredAt ?? now()->toIso8601String(),
        ];
    }

    private function mask(string $email): string
    {
        $at = strpos($email, '@');

        if ($at === false || $at < 1) {
            return '***';
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at);

        if (strlen($local) <= 2) {
            return $local[0].'***'.$domain;
        }

        return $local[0].'***'.substr($local, -1).$domain;
    }
}
