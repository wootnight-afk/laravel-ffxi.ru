<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\LocalizesMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserEmailChangedForAdminNotification extends Notification
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
        $profileUrl = route('players.show', ['user' => $this->user->name]);
        $locale = $this->mailLocale($notifiable);

        return (new MailMessage)
            ->subject($this->mailLine('user_email_changed_for_admin.subject', [], $locale))
            ->greeting($this->mailLine('user_email_changed_for_admin.greeting', [], $locale))
            ->line($this->mailLine('user_email_changed_for_admin.intro', [], $locale))
            ->line($this->mailLine('fields.user', [
                'name' => $this->user->name,
                'id' => $this->user->getKey(),
            ], $locale))
            ->line($this->mailLine('fields.old', ['value' => $this->oldEmail], $locale))
            ->line($this->mailLine('fields.new', ['value' => $this->newEmail], $locale))
            ->line($this->mailLine('fields.when', ['value' => $when], $locale))
            ->line($this->mailLine('fields.ip', ['value' => $this->ip], $locale))
            ->line($this->mailLine('fields.user_agent', ['value' => $this->userAgent], $locale))
            ->action($this->mailLine('user_email_changed_for_admin.action', [], $locale), $profileUrl)
            ->salutation($this->mailLine('salutation_security', [], $locale));
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
