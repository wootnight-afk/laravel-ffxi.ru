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
 */
class EnsureAdminMultiFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
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
}
