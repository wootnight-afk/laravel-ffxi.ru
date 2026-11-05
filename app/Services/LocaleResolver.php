<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves the interface locale (ADR-011 §2.2).
 *
 * Authenticated: users.locale → session → cookie `locale` → Accept-Language → ru.
 * Guest: session → cookie `locale` → Accept-Language → ru.
 *
 * The resolver is read-only: automatic detection is never persisted to the
 * profile. Unknown values from any source are ignored instead of throwing.
 * Without an HTTP request (CLI, queue, scheduler, unit tests) it returns `ru`.
 */
final class LocaleResolver
{
    public const COOKIE = 'locale';

    public function resolve(?User $user = null): string
    {
        if ($user !== null) {
            $stored = $this->normalize($user->locale);

            if ($stored !== null) {
                return $stored;
            }
        }

        return $this->fromSession()
            ?? $this->fromCookie()
            ?? $this->fromAcceptLanguage()
            ?? Locale::Ru->value;
    }

    private function normalize(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }

        return in_array($locale, Locale::values(), true) ? $locale : null;
    }

    private function fromSession(): ?string
    {
        $request = $this->request();

        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get(LocalePreference::SESSION_KEY);

        return is_string($value) ? $this->normalize($value) : null;
    }

    private function fromCookie(): ?string
    {
        $value = $this->request()->cookie(self::COOKIE);

        return is_string($value) ? $this->normalize($value) : null;
    }

    /**
     * First supported locale from the Accept-Language header, ordered by
     * quality. Only the primary subtag is matched (`en-US` → `en`).
     */
    private function fromAcceptLanguage(): ?string
    {
        $request = $this->request();

        // The console placeholder request (CLI, queue, scheduler) carries a
        // synthetic Accept-Language header; only real HTTP requests have a
        // session, so the header is ignored outside HTTP.
        if (! $request->hasSession()) {
            return null;
        }

        $header = $request->headers->get('Accept-Language');

        if (! is_string($header) || trim($header) === '') {
            return null;
        }

        $candidates = [];

        foreach (explode(',', $header) as $position => $part) {
            $segments = explode(';', $part);
            $tag = strtolower(trim($segments[0]));

            if ($tag === '') {
                continue;
            }

            $quality = 1.0;

            foreach (array_slice($segments, 1) as $parameter) {
                $parameter = trim($parameter);

                if (str_starts_with($parameter, 'q=')) {
                    $quality = (float) substr($parameter, 2);
                }
            }

            if ($quality <= 0) {
                continue;
            }

            $primary = explode('-', explode('_', $tag, 2)[0], 2)[0];
            $locale = $this->normalize($primary);

            if ($locale === null) {
                continue;
            }

            $candidates[] = [
                'locale' => $locale,
                'quality' => $quality,
                'position' => $position,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => [$right['quality'], $left['position']]
                <=> [$left['quality'], $right['position']],
        );

        return $candidates[0]['locale'];
    }

    private function request(): Request
    {
        // Outside HTTP (CLI, queue, scheduler) the container resolves a fresh
        // Request without session or cookies, so the resolver returns `ru`.
        return app()->make('request');
    }
}
