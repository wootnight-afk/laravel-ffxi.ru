<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireMfa;
use App\Services\AuditLogger;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Self-service MFA management from `/cabinet/security` (ADR-009 §2.11).
 *
 * Every action operates on the authenticated user only; no user identifier is
 * ever taken from the request. TOTP secrets, QR payloads and recovery codes are
 * never written to the audit log, email or application log.
 */
class MfaController extends Controller
{
    private const SETUP_SECRET_KEY = 'mfa_setup_secret';

    private const RECOVERY_CODES_KEY = 'mfa_recovery_codes';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Start the MFA setup flow by generating a pending TOTP secret.
     */
    public function setup(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Never overwrite an active secret: disable it first (with re-auth).
        if (filled($user->getAppAuthenticationSecret())) {
            return $this->backToSecurity()->withErrors([
                'mfa' => 'MFA уже настроена. Сначала отключите текущую.',
            ]);
        }

        $request->session()->put(
            self::SETUP_SECRET_KEY,
            AppAuthentication::make()->generateSecret(),
        );

        return $this->backToSecurity();
    }

    /**
     * Confirm the pending secret with a TOTP code and enable MFA.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'code.required' => 'Введите код из приложения.',
            'code.regex' => 'Код должен содержать 6 цифр.',
        ]);

        $user = $request->user();
        $secret = $request->session()->get(self::SETUP_SECRET_KEY);

        if (filled($user->getAppAuthenticationSecret())) {
            return $this->backToSecurity()->withErrors([
                'mfa' => 'MFA уже настроена.',
            ]);
        }

        if (! is_string($secret) || $secret === '') {
            return $this->backToSecurity()->withErrors([
                'code' => 'Сессия настройки MFA истекла. Начните заново.',
            ]);
        }

        $authentication = AppAuthentication::make();

        if (! $authentication->verifyCode($validated['code'], $secret)) {
            throw ValidationException::withMessages([
                'code' => 'Неверный код. Попробуйте снова.',
            ]);
        }

        $plainRecoveryCodes = $authentication->generateRecoveryCodes();

        $user->saveAppAuthenticationSecret($secret);
        $user->saveAppAuthenticationRecoveryCodes($this->hashCodes($plainRecoveryCodes));

        $this->audit->log(
            action: 'mfa.enabled',
            subject: $user,
            old: null,
            new: ['mfa_enabled' => true],
            actor: $user,
        );

        $request->session()->forget(self::SETUP_SECRET_KEY);

        return $this->backToSecurity()->with(self::RECOVERY_CODES_KEY, $plainRecoveryCodes);
    }

    /**
     * Disable MFA for the authenticated user (re-auth required).
     */
    public function disable(Request $request): RedirectResponse
    {
        $this->validatePassword($request);

        $user = $request->user();

        // Idempotent: nothing to disable.
        if (blank($user->getAppAuthenticationSecret())) {
            return $this->backToSecurity()->with('status', 'MFA не была настроена.');
        }

        $user->saveAppAuthenticationSecret(null);
        $user->saveAppAuthenticationRecoveryCodes(null);

        // Drop any MFA verification for this user: it must not survive a
        // re-enable within the same session (ADR-009 §2.5).
        $request->session()->forget([
            RequireMfa::SESSION_USER_ID_KEY,
            RequireMfa::SESSION_VERIFIED_AT_KEY,
        ]);

        $this->audit->log(
            action: 'mfa.disabled',
            subject: $user,
            old: ['mfa_enabled' => true],
            new: ['mfa_enabled' => false],
            actor: $user,
        );

        return $this->backToSecurity()->with('status', 'MFA отключена.');
    }

    /**
     * Regenerate recovery codes for the authenticated user (re-auth required).
     *
     * No dedicated audit event is written: ADR-009 §2.12 defines a minimal set
     * that does not include recovery-code regeneration.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $this->validatePassword($request);

        $user = $request->user();

        if (blank($user->getAppAuthenticationSecret())) {
            return $this->backToSecurity()->withErrors([
                'password' => 'MFA не настроена.',
            ]);
        }

        $plainRecoveryCodes = AppAuthentication::make()->generateRecoveryCodes();

        $user->saveAppAuthenticationRecoveryCodes($this->hashCodes($plainRecoveryCodes));

        return $this->backToSecurity()->with(self::RECOVERY_CODES_KEY, $plainRecoveryCodes);
    }

    /**
     * Abort a pending setup without enabling MFA.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SETUP_SECRET_KEY);

        return $this->backToSecurity();
    }

    private function validatePassword(Request $request): void
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ], [
            'password.required' => 'Введите текущий пароль.',
            'password.current_password' => 'Неверный текущий пароль.',
        ]);
    }

    /**
     * @param  array<string>  $codes
     * @return array<string>
     */
    private function hashCodes(array $codes): array
    {
        return array_map(
            fn (string $code): string => Hash::make($code),
            $codes,
        );
    }

    private function backToSecurity(): RedirectResponse
    {
        return redirect()->route('cabinet.tab', ['tab' => 'security']);
    }
}
