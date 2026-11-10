<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\Locale;

/**
 * Resolves the language of an outgoing email from the recipient profile.
 *
 * Emails have no browser locale, so an empty `users.locale` falls back to the
 * application fallback locale instead of the request locale.
 */
trait LocalizesMail
{
    protected function mailLocale(object $notifiable): string
    {
        $locale = $notifiable->locale ?? null;

        if (is_string($locale) && in_array($locale, Locale::values(), true)) {
            return $locale;
        }

        return (string) config('app.fallback_locale', Locale::Ru->value);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    protected function mailLine(string $key, array $replace, string $locale): string
    {
        return (string) trans('site.notifications.mail.'.$key, $replace, $locale);
    }
}
