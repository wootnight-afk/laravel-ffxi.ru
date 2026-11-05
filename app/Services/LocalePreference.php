<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Throwable;

/**
 * Persists an explicit interface locale and mirrors it into the session.
 *
 * The database is written first: a database failure propagates and the session
 * is not touched. A session failure is logged instead of being reported as a
 * database failure, because users.locale is the source of truth.
 */
final class LocalePreference
{
    public const SESSION_KEY = 'locale';

    public function __construct(private readonly Session $session) {}

    public function update(User $user, ?Locale $locale): void
    {
        $user->locale = $locale?->value;
        $user->save();

        try {
            if ($locale === null) {
                $this->session->forget(self::SESSION_KEY);
            } else {
                $this->session->put(self::SESSION_KEY, $locale->value);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
