<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the resolved interface locale to the current request.
 *
 * Read-only: the middleware never writes the detected locale to the session,
 * the profile or a cookie (ADR-011 §2.2).
 */
final class SetLocale
{
    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolver->resolve($request->user()));

        return $next($request);
    }
}
