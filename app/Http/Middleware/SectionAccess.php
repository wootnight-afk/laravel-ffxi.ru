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

        // Guest — a section closed for guests means "log in first", not
        // "not found" (frontend-spec.md §3.6/§9.17, ADR-010). The framework's
        // Redirector::guest() keeps the intended URL for GET only; HEAD is not
        // guaranteed and no custom logic is added.
        $guestSections = $this->settings->get('guest_sections', []);

        if (! is_array($guestSections) || ! ($guestSections[$key] ?? false)) {
            $isReadOnlyRequest = $request->isMethod('GET') || $request->isMethod('HEAD');

            if ($isReadOnlyRequest && ! $request->expectsJson()) {
                return redirect()
                    ->guest(route('login'))
                    ->with('status', 'Для доступа к этому разделу требуется авторизация.');
            }

            abort(403);
        }

        return $next($request);
    }
}
