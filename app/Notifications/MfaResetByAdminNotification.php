<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
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
        return (new MailMessage)
            ->subject('MFA для вашего аккаунта сброшена администратором')
            ->greeting('Здравствуйте!')
            ->line('Двухфакторная аутентификация (MFA) для вашего аккаунта FFXI Phoenix была сброшена администратором.')
            ->line('Чтобы снова защитить аккаунт, настройте MFA заново в разделе безопасности личного кабинета.')
            ->action('Настроить MFA', route('cabinet.tab', ['tab' => 'security']))
            ->line('Если вы не запрашивали сброс — свяжитесь с администрацией.')
            ->salutation('— FFXI Phoenix');
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
