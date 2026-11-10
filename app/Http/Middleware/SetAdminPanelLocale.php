<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the resolved locale to the admin panel (ADR-011 §2.2).
 *
 * The panel registers its own middleware stack, so the web group's SetLocale
 * does not run for panel and Livewire requests; this middleware keeps both
 * consistent and is also registered as persistent in AdminPanelProvider.
 */
final class SetAdminPanelLocale
{
    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolver->resolve($request->user());

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
