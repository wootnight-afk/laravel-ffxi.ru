<?php

namespace App\Http\Middleware;

use App\Services\CharacterContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active character once per request (ADR-013 §2.3, STAGE-2 §4).
 *
 * It runs in the `web` group after the session and the locale are ready, and
 * only shares the value: the write paths live in CharacterContext and are
 * reached from explicit POST/DELETE endpoints. No GET request resolves the
 * context by writing anything, and the middleware neither replaces nor weakens
 * `section.access` and `mfa.required`.
 *
 * The list of characters for the sidebar is not loaded here: the panel asks
 * CharacterSwitcher for it, so pages outside Players pay nothing extra.
 */
class ResolveCharacterContext
{
    public function __construct(private readonly CharacterContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        View::share('activeCharacter', $user === null ? null : $this->context->for($user));

        return $next($request);
    }
}
