<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves the display timezone without ever mutating stored preferences.
 *
 * Priority (ADR-011 §2.3): users.timezone → cookie `tz` → UTC. The session is
 * deliberately not consulted: the timezone is read on every render, so caching
 * it in the session is forbidden. Without an HTTP request (CLI, queue,
 * scheduler, unit tests) the resolver returns UTC.
 *
 * Registered as a singleton: resolve() and isFallback() describe the same
 * resolution, so they must be called on one shared instance within a request.
 */
final class TimezoneResolver
{
    public const COOKIE = 'tz';

    private bool $fallback = true;

    public function resolve(?User $user = null): string
    {
        $stored = $this->valid($user?->timezone);

        if ($stored !== null) {
            return $this->remember($stored, false);
        }

        $cookie = $this->valid($this->cookieValue());

        if ($cookie !== null) {
            return $this->remember($cookie, false);
        }

        return $this->remember('UTC', true);
    }

    /**
     * Whether the last resolve() call fell back to UTC instead of using a
     * stored preference or the browser cookie.
     */
    public function isFallback(): bool
    {
        return $this->fallback;
    }

    private function remember(string $timezone, bool $fallback): string
    {
        $this->fallback = $fallback;

        return $timezone;
    }

    private function valid(?string $timezone): ?string
    {
        if ($timezone === null || $timezone === '') {
            return null;
        }

        return TimezoneOptions::isValid($timezone) ? $timezone : null;
    }

    private function cookieValue(): ?string
    {
        $value = $this->request()->cookie(self::COOKIE);

        // JavaScript encodes the IANA identifier (`Europe/Moscow` becomes
        // `Europe%2FMoscow`); PHP decodes request cookies, but a double-encoded
        // value must still resolve to a valid identifier.
        return is_string($value) ? rawurldecode($value) : null;
    }

    private function request(): Request
    {
        // Outside HTTP (CLI, queue, scheduler) the container resolves a fresh
        // Request without cookies, so the resolver naturally returns UTC.
        return app()->make('request');
    }
}
