<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Locale;
use App\Services\LocaleResolver;
use Illuminate\Contracts\Cookie\Factory as CookieFactory;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Builds the `locale` cookie for an explicit language choice (ADR-011 §2.2).
 *
 * The cookie is plain (excluded from cookie encryption), lives for one year and
 * mirrors the value the user picked; `null` (auto) removes it so that a stale
 * guest value cannot override Accept-Language.
 */
final class LocaleCookie
{
    /** One year, in minutes. */
    public const MINUTES = 60 * 24 * 365;

    public static function make(Locale $locale, bool $secure): Cookie
    {
        return app(CookieFactory::class)->make(
            name: LocaleResolver::COOKIE,
            value: $locale->value,
            minutes: self::MINUTES,
            path: '/',
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public static function forget(): Cookie
    {
        return app(CookieFactory::class)->forget(LocaleResolver::COOKIE);
    }
}
