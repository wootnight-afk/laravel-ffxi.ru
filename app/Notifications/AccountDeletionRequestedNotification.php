<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionRequestedNotification extends Notification
{
    use LocalizesMail;
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
        $locale = $this->mailLocale($notifiable);

        return (new MailMessage)
            ->subject($this->mailLine('account_deletion_requested.subject', [], $locale))
            ->greeting($this->mailLine('account_deletion_requested.greeting', [], $locale))
            ->line($this->mailLine('account_deletion_requested.intro', [], $locale))
            ->line($this->mailLine('fields.when', ['value' => $when], $locale))
            ->line($this->mailLine('account_deletion_requested.reason', ['reason' => $this->reason], $locale))
            ->line($this->mailLine('account_deletion_requested.closed', [], $locale))
            ->line($this->mailLine('account_deletion_requested.request_only', [], $locale))
            ->line($this->mailLine('account_deletion_requested.final', [], $locale))
            ->line($this->mailLine('account_deletion_requested.changed_mind', [], $locale))
            ->action($this->mailLine('account_deletion_requested.action', [], $locale), route('contacts'))
            ->salutation($this->mailLine('salutation', [], $locale));
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
