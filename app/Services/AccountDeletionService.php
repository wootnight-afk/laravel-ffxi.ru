<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountDeletionRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AccountDeletionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AdminNotifier $adminNotifier,
    ) {}

    /**
     * Mark the account as pending deletion (frontend-spec §6.12, step 1–2).
     *
     * Idempotent: if the account is already in deletion_requested state,
     * the method exits without any side effects.
     *
     * Never throws on notification delivery failure: the state change is
     * already committed, so the caller's request should still succeed.
     */
    public function request(User $user, string $reason): void
    {
        if ($user->isDeletionRequested()) {
            return;
        }

        $reason = mb_substr($reason, 0, 500);

        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill([
                'status' => UserStatus::DeletionRequested,
                'deletion_requested_at' => now(),
                'deletion_reason' => $reason,
                'remember_token' => Str::random(60),
            ])->save();

            // Invalidate other devices' sessions (database session driver only).
            // The current request is invalidated by the controller after this call.
            if (config('session.driver') === 'database') {
                DB::table('sessions')
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            // TODO(stage-7): When Events are implemented, on deletion_requested:
            //   - cancel events where this user is the leader; notify participants;
            //   - remove the user from joined events (or mark 'left'); notify leaders.
            //
            // TODO(stage-8): Full deletion (PII wipe, avatar cleanup, cascade
            //   soft-delete) and restoration are handled by the Filament UserResource.
        });

        $this->audit->log(
            action: 'account.deletion_requested',
            subject: $user,
            old: ['status' => UserStatus::Active->value],
            new: ['status' => UserStatus::DeletionRequested->value],
            actor: $user,
        );

        try {
            $user->notify(new AccountDeletionRequestedNotification($reason));
        } catch (Throwable $e) {
            Log::error('Failed to send account deletion request notification', [
                'user_id' => $user->getKey(),
                'context' => 'danger.request.user',
                'exception' => $e->getMessage(),
            ]);
        }

        // Handles its own logging and swallows its own exceptions.
        $this->adminNotifier->notifyAboutUserDeletionRequest($user, $reason);
    }
}
