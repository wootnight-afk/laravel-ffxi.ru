<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\RequireMfa;
use App\Services\AuditLogger;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Unified MFA challenge for every role (ADR-009 §2.5).
 *
 * Blade + plain POST. Supports TOTP codes and single-use recovery codes.
 * Secrets, QR payloads and recovery codes are never written to the audit log.
 */
class MfaChallengeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Nothing to challenge when MFA is not configured.
        if ($user === null || blank($user->getAppAuthenticationSecret())) {
            return redirect()->intended(route('home'));
        }

        // Already verified for this user (ADR-009 §2.5).
        if ($this->isVerified($request, $user->getKey())) {
            return redirect()->intended(route('home'));
        }

        return view('mfa.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || blank($user->getAppAuthenticationSecret())) {
            return redirect()->intended(route('home'));
        }

        $usesRecoveryCode = $request->boolean('use_recovery_code');

        if ($usesRecoveryCode) {
            $validated = $request->validate([
                'recovery_code' => ['required', 'string'],
            ], [
                'recovery_code.required' => 'Введите резервный код.',
            ]);

            $valid = AppAuthentication::make()->verifyRecoveryCode(
                $validated['recovery_code'],
                $user,
            );
        } else {
            $validated = $request->validate([
                'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            ], [
                'code.required' => 'Введите код из приложения.',
                'code.regex' => 'Код должен содержать 6 цифр.',
            ]);

            $valid = AppAuthentication::make()->verifyCode(
                $validated['code'],
                $user->getAppAuthenticationSecret(),
                shouldPreventCodeReuse: true,
            );
        }

        if (! $valid) {
            $this->audit->log(
                action: 'mfa.challenge_failure',
                subject: $user,
                old: null,
                new: ['mfa_verified' => false],
                actor: $user,
            );

            $field = $usesRecoveryCode ? 'recovery_code' : 'code';

            throw ValidationException::withMessages([
                $field => 'Неверный код.',
            ]);
        }

        $request->session()->put(RequireMfa::SESSION_USER_ID_KEY, $user->getKey());
        $request->session()->put(RequireMfa::SESSION_VERIFIED_AT_KEY, now()->timestamp);

        $this->audit->log(
            action: 'mfa.challenge_success',
            subject: $user,
            old: null,
            new: ['mfa_verified' => true],
            actor: $user,
        );

        return redirect()->intended(route('home'));
    }

    private function isVerified(Request $request, int|string $userId): bool
    {
        return $request->session()->get(RequireMfa::SESSION_USER_ID_KEY) === $userId;
    }
}
