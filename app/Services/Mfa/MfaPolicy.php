<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Models\User;
use App\Services\SettingsRepository;

/**
 * Effective MFA policy (ADR-009 §2.16 — production invariant).
 *
 * Resolves the runtime MFA enforcement from the database settings, hardened by
 * the environment: in production MFA is always enforced (fail-closed) and can
 * be neither disabled globally nor exempted for admins. Outside production the
 * database settings are authoritative, which keeps local development and manual
 * acceptance able to log in by password.
 *
 * The environment is used for hardening only — never as a bypass.
 */
final class MfaPolicy
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Whether MFA enforcement is active at all.
     *
     * Production: always true. Otherwise: the stored setting (default true).
     */
    public function globalEnabled(): bool
    {
        if (app()->environment('production')) {
            return true;
        }

        return $this->settings->bool('mfa_global_enabled', true);
    }

    /**
     * Whether the given user is required to have MFA configured.
     *
     * Only admins can be required. Production: always true for admins.
     * Otherwise: the stored setting (default false) and admin role.
     */
    public function adminMfaRequired(User $user): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        if (app()->environment('production')) {
            return true;
        }

        return $this->settings->bool('admin_2fa_required', false);
    }
}
