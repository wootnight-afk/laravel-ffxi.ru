<?php

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SectionAccess
{
    public function __construct(
        protected SettingsRepository $settings,
    ) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        $user = $request->user();

        if ($user !== null) {
            if ($user->isAdmin()) {
                return $next($request);
            }

            $permission = "section.{$key}.view";

            if (! $user->can($permission)) {
                abort(403);
            }

            return $next($request);
        }

        // Guest — frontend-spec §9.17 requires 404, not a login redirect.
        $guestSections = $this->settings->get('guest_sections', []);

        if (! is_array($guestSections) || ! ($guestSections[$key] ?? false)) {
            abort(404);
        }

        return $next($request);
    }
}
