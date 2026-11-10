<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\LocalizesMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserDeletionRequestedForAdminNotification extends Notification
{
    use LocalizesMail;
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
        $locale = $this->mailLocale($notifiable);

        // NOTE(stage-8): Add an action link to the Filament UserResource
        // once the admin panel is available. Profil link is intentionally
        // omitted: PlayerController returns 404 for deletion_requested users.
        return (new MailMessage)
            ->subject($this->mailLine('user_deletion_requested_for_admin.subject', [], $locale))
            ->greeting($this->mailLine('user_deletion_requested_for_admin.greeting', [], $locale))
            ->line($this->mailLine('user_deletion_requested_for_admin.intro', [], $locale))
            ->line($this->mailLine('fields.user', [
                'name' => $this->user->name,
                'id' => $this->user->getKey(),
            ], $locale))
            ->line($this->mailLine('fields.reason', ['value' => $this->reason], $locale))
            ->line($this->mailLine('fields.when', ['value' => $when], $locale))
            ->line($this->mailLine('fields.ip', ['value' => $this->ip], $locale))
            ->line($this->mailLine('fields.user_agent', ['value' => $this->userAgent], $locale))
            ->line($this->mailLine('user_deletion_requested_for_admin.status', [], $locale))
            ->salutation($this->mailLine('salutation_security', [], $locale));
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
