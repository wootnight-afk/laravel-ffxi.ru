<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\UserDeletionRequestedForAdminNotification;
use App\Notifications\UserEmailChangedForAdminNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class AdminNotifier
{
    /**
     * Notify every admin (except the initiator) that a user changed their email.
     *
     * Never throws: a delivery failure is logged but does not roll back
     * the initiating operation (email has already been changed).
     */
    public function notifyAboutUserEmailChange(
        User $initiator,
        string $oldEmail,
        string $newEmail,
    ): void {
        $recipients = User::role('admin')
            ->whereKeyNot($initiator->getKey())
            ->get();

        if ($recipients->isEmpty()) {
            Log::info('AdminNotifier: no admin recipients for email change', [
                'initiator_id' => $initiator->getKey(),
            ]);

            return;
        }

        try {
            Notification::send(
                $recipients,
                new UserEmailChangedForAdminNotification(
                    user: $initiator,
                    oldEmail: $oldEmail,
                    newEmail: $newEmail,
                    ip: $this->resolveIp() ?? 'unknown',
                    userAgent: $this->resolveUserAgent() ?? 'unknown',
                ),
            );
        } catch (Throwable $e) {
            Log::error('AdminNotifier: failed to send email change notification', [
                'initiator_id' => $initiator->getKey(),
                'recipients' => $recipients->pluck('id')->all(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify every admin (except the initiator) that a user requested account deletion.
     *
     * Never throws: a delivery failure is logged but does not roll back
     * the initiating operation (status has already been set to deletion_requested).
     */
    public function notifyAboutUserDeletionRequest(
        User $initiator,
        string $reason,
    ): void {
        $recipients = User::role('admin')
            ->whereKeyNot($initiator->getKey())
            ->get();

        if ($recipients->isEmpty()) {
            Log::info('AdminNotifier: no admin recipients for deletion request', [
                'initiator_id' => $initiator->getKey(),
            ]);

            return;
        }

        try {
            Notification::send(
                $recipients,
                new UserDeletionRequestedForAdminNotification(
                    user: $initiator,
                    reason: $reason,
                    ip: $this->resolveIp() ?? 'unknown',
                    userAgent: $this->resolveUserAgent() ?? 'unknown',
                ),
            );
        } catch (Throwable $e) {
            Log::error('AdminNotifier: failed to send deletion request notification', [
                'initiator_id' => $initiator->getKey(),
                'recipients' => $recipients->pluck('id')->all(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function resolveIp(): ?string
    {
        return request()->ip();
    }

    private function resolveUserAgent(): ?string
    {
        return request()->userAgent();
    }
}
