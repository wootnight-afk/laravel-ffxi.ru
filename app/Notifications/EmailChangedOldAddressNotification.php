<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\LocalizesMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangedOldAddressNotification extends Notification
{
    use LocalizesMail;
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
        $locale = $this->mailLocale($notifiable);

        return (new MailMessage)
            ->subject($this->mailLine('email_changed_old_address.subject', [], $locale))
            ->greeting($this->mailLine('email_changed_old_address.greeting', ['name' => $this->user->name], $locale))
            ->line($this->mailLine('email_changed_old_address.intro', [], $locale))
            ->line($this->mailLine('fields.old', ['value' => $this->oldEmail], $locale))
            ->line($this->mailLine('fields.new', ['value' => $maskedNew], $locale))
            ->line($this->mailLine('fields.when', ['value' => $when], $locale))
            ->line($this->mailLine('fields.ip', ['value' => $this->ip], $locale))
            ->line($this->mailLine('fields.user_agent', ['value' => $this->userAgent], $locale))
            ->line($this->mailLine('email_changed_old_address.warning', [], $locale))
            ->action($this->mailLine('email_changed_old_address.action', [], $locale), route('password.request'))
            ->salutation($this->mailLine('salutation', [], $locale));
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
