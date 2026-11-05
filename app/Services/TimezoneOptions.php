<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeZone;

/**
 * Single source of IANA timezone identifiers and display labels.
 *
 * Labels show the current UTC offset, so DST and historical transitions are
 * reflected at render time instead of being stored.
 */
final class TimezoneOptions
{
    /**
     * Deterministic IANA list with UTC first.
     *
     * @return array<int, string>
     */
    public static function identifiers(): array
    {
        $identifiers = DateTimeZone::listIdentifiers();
        sort($identifiers, SORT_STRING);

        return array_values(array_unique(array_merge(['UTC'], $identifiers)));
    }

    /**
     * Region => [IANA identifier => label]. UTC is always the first group.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::identifiers() as $identifier) {
            if ($identifier === 'UTC') {
                continue;
            }

            $region = explode('/', $identifier, 2)[0];
            $groups[$region][$identifier] = self::label($identifier);
        }

        ksort($groups, SORT_STRING);

        return ['UTC' => ['UTC' => self::label('UTC')]] + $groups;
    }

    /**
     * Flat identifier => label list.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::identifiers() as $identifier) {
            $options[$identifier] = self::label($identifier);
        }

        return $options;
    }

    public static function label(string $identifier): string
    {
        return sprintf('%s (UTC %s)', $identifier, self::offset($identifier));
    }

    /**
     * Current UTC offset for the identifier, e.g. "+03:00".
     */
    public static function offset(string $identifier): string
    {
        return now()
            ->setTimezone(new DateTimeZone($identifier))
            ->format('P');
    }

    public static function isValid(?string $identifier): bool
    {
        if ($identifier === null || $identifier === '') {
            return false;
        }

        return in_array($identifier, DateTimeZone::listIdentifiers(), true);
    }
}
