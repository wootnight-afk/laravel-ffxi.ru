<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\LocalizesMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies a user that an administrator reset their MFA (ADR-009 §2.10).
 *
 * The message never contains the secret, QR payload or recovery codes: it only
 * tells the user that MFA was disabled and how to set it up again.
 */
class MfaResetByAdminNotification extends Notification
{
    use LocalizesMail;
    use Queueable;

    public function __construct(
        public readonly User $actor,
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
        $locale = $this->mailLocale($notifiable);

        return (new MailMessage)
            ->subject($this->mailLine('mfa_reset_by_admin.subject', [], $locale))
            ->greeting($this->mailLine('mfa_reset_by_admin.greeting', [], $locale))
            ->line($this->mailLine('mfa_reset_by_admin.intro', [], $locale))
            ->line($this->mailLine('mfa_reset_by_admin.hint', [], $locale))
            ->action($this->mailLine('mfa_reset_by_admin.action', [], $locale), route('cabinet.tab', ['tab' => 'security']))
            ->line($this->mailLine('mfa_reset_by_admin.warning', [], $locale))
            ->salutation($this->mailLine('salutation', [], $locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'mfa.admin_reset',
            'actor_id' => $this->actor->getKey(),
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
