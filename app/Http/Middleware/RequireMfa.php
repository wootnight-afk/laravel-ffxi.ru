<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified MFA enforcement (ADR-009 §2.3–§2.4).
 *
 * Applied to `/players` and the admin panel. Enforcement is opt-in per user:
 * a user without a configured secret is only challenged when they are an admin
 * and `admin_2fa_required` is on. The verification state is bound to the
 * authenticated user id so it cannot be reused after a user switch (T24).
 *
 * Escape-hatches and pre-auth panel routes are always reachable.
 */
class RequireMfa
{
    /** Session key holding the id of the user that completed MFA. */
    public const SESSION_USER_ID_KEY = 'mfa_verified_user_id';

    /** Session key holding the timestamp of the successful verification. */
    public const SESSION_VERIFIED_AT_KEY = 'mfa_verified_at';

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Global gate (ADR-009 §2.1) — default enabled.
        if (! app(SettingsRepository::class)->bool('mfa_global_enabled', true)) {
            return $next($request);
        }

        // 2. Escape-hatches and technical pre-auth routes (ADR-009 §2.4).
        if ($this->isEscapeHatch($request)) {
            return $next($request);
        }

        // 3. Authentication is handled by the `auth` middleware.
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // 4. Editor profile page is outside the MFA-protected matrix
        //    (ADR-009 §2.3). Admins are always covered.
        if (
            $request->is('admin/profile')
            && $user->hasRole('editor')
            && ! $user->hasRole('admin')
        ) {
            return $next($request);
        }

        // 5. Enforcement.
        $hasMfa = filled($user->getAppAuthenticationSecret());
        $verified = $request->session()->get(self::SESSION_USER_ID_KEY) === $user->getKey();

        if ($hasMfa && ! $verified) {
            return redirect()->guest(route('mfa.challenge'));
        }

        if (! $hasMfa) {
            $required = app(SettingsRepository::class)->bool('admin_2fa_required', false)
                && $user->hasRole('admin');

            if ($required) {
                return redirect()->guest(route('cabinet.tab', ['tab' => 'security']));
            }
        }

        return $next($request);
    }

    /**
     * Routes that must stay reachable without completed MFA.
     */
    private function isEscapeHatch(Request $request): bool
    {
        return $request->is(
            'mfa/challenge',
            'mfa/challenge/*',
            'admin/login',
            'admin/logout',
            'admin/settings',
            'admin/settings/*',
            'cabinet/security',
            'cabinet/security/*',
        );
    }
}
