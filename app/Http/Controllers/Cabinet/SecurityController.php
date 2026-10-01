<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeEmailRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Notifications\EmailChangedOldAddressNotification;
use App\Notifications\PasswordChangedNotification;
use App\Services\AdminNotifier;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SecurityController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AdminNotifier $adminNotifier,
    ) {}

    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $ip = $request->ip() ?? 'unknown';
        $userAgent = $request->userAgent() ?? 'unknown';

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $this->audit->log(
            action: 'password.changed',
            subject: $user,
            old: null,
            new: ['password_changed' => true],
            actor: $user,
        );

        try {
            $user->notify(new PasswordChangedNotification($ip, $userAgent));
        } catch (Throwable $e) {
            Log::error('Failed to send password changed notification', [
                'user_id' => $user->getKey(),
                'context' => 'security.password',
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('cabinet.tab', ['tab' => 'security'])
            ->with('status', 'Пароль успешно изменён.');
    }

    public function updateEmail(ChangeEmailRequest $request): RedirectResponse
    {
        $user = $request->user();
        $oldEmail = $user->email;
        $newEmail = $request->validated('email');
        $ip = $request->ip() ?? 'unknown';
        $userAgent = $request->userAgent() ?? 'unknown';

        DB::transaction(function () use ($user, $newEmail): void {
            $user->forceFill([
                'email' => $newEmail,
                'email_verified_at' => null,
            ])->save();
        });

        $this->audit->log(
            action: 'email.changed',
            subject: $user,
            old: ['email' => $oldEmail],
            new: ['email' => $newEmail],
            actor: $user,
        );

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::error('Failed to send verification email after address change', [
                'user_id' => $user->getKey(),
                'context' => 'security.email.verification',
                'exception' => $e->getMessage(),
            ]);
        }

        try {
            Notification::route('mail', $oldEmail)
                ->notify(new EmailChangedOldAddressNotification(
                    user: $user,
                    oldEmail: $oldEmail,
                    newEmail: $newEmail,
                    ip: $ip,
                    userAgent: $userAgent,
                ));
        } catch (Throwable $e) {
            Log::error('Failed to send old-address security notification', [
                'user_id' => $user->getKey(),
                'context' => 'security.email.old_address',
                'exception' => $e->getMessage(),
            ]);
        }

        $this->adminNotifier->notifyAboutUserEmailChange($user, $oldEmail, $newEmail);

        return redirect()
            ->route('verification.notice')
            ->with('status', 'Email изменён. Подтвердите новый адрес.');
    }
}
