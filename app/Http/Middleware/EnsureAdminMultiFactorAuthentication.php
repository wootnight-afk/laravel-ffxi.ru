<?php

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces MFA setup for admins when `admin_2fa_required` is enabled.
 *
 * Filament evaluates the panel's `isRequired` condition while routes are
 * being registered (before the request is authenticated), so a role/settings
 * dependent closure cannot be used there. The panel therefore registers this
 * middleware statically and the dynamic decision is made per request here.
 *
 * `/admin/settings` is an escape-hatch (ADR-009 §2.4): it must stay reachable
 * without completed MFA so an admin can always manage the global flags
 * (including turning the requirement off) and never lock themselves out.
 */
class EnsureAdminMultiFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isEscapeHatch($request)) {
            return $next($request);
        }

        $user = Filament::auth()->user();

        if ($user === null) {
            return $next($request);
        }

        $required = app(SettingsRepository::class)->bool('admin_2fa_required', false)
            && $user->hasRole('admin');

        if (! $required) {
            return $next($request);
        }

        if (MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }

    /**
     * `/admin/settings` and its sub-paths are reachable without completed MFA.
     */
    private function isEscapeHatch(Request $request): bool
    {
        $panelPath = trim((string) Filament::getCurrentPanel()?->getPath(), '/');
        $settingsPath = ($panelPath !== '' ? $panelPath : 'admin').'/settings';

        return $request->is($settingsPath, $settingsPath.'/*');
    }
}
