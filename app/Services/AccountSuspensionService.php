<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountSuspendedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AccountSuspensionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AdminNotifier $adminNotifier,
    ) {}

    public function suspend(User $user, string $reason): void
    {
        if ($user->isSuspended()) {
            return;
        }

        $reason = mb_substr($reason, 0, 500);

        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill([
                'status' => UserStatus::Suspended,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::table('sessions')
                    ->where('user_id', $user->getKey())
                    ->delete();
            }
        });

        $this->audit->log(
            action: 'account.suspended',
            subject: $user,
            old: ['status' => UserStatus::Active->value],
            new: ['status' => UserStatus::Suspended->value],
            actor: $user,
        );

        $suspendedAt = $user->suspended_at?->toIso8601String() ?? now()->toIso8601String();

        try {
            $user->notify(new AccountSuspendedNotification($reason, $suspendedAt));
        } catch (Throwable $e) {
            Log::error('Failed to send account suspension notification', [
                'user_id' => $user->getKey(),
                'context' => 'danger.suspend.user',
                'exception' => $e->getMessage(),
            ]);
        }

        $this->adminNotifier->notifyAboutUserSuspension($user, $reason);
    }
}
