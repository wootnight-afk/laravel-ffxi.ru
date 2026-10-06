<?php

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to the admin panel to a configured IP/CIDR allowlist.
 *
 * An empty allowlist means allow-all (opt-in). Only `$request->ip()` is
 * consulted; proxy headers are not trusted in Stage 8 (context.md §11).
 */
class EnsureAdminIpAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = app(SettingsRepository::class)->get('admin_ip_allowlist', []);

        if (! is_array($allowlist) || $allowlist === []) {
            return $next($request);
        }

        $ip = $request->ip();

        if ($ip !== null && IpUtils::checkIp($ip, $allowlist)) {
            return $next($request);
        }

        abort(403);
    }
}
