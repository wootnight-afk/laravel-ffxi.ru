<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeZone;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of IANA timezone identifiers and display labels.
 *
 * Labels show the current UTC offset, so DST and historical transitions are
 * reflected at render time instead of being stored.
 *
 * The grouped() and options() results are cached for 1 hour because the
 * underlying identifier list only changes when PHP/tzdata is updated. The
 * offset() method always computes the current offset — Alpine recomputes
 * it client-side on every tick, so the cached label is a server-side hint
 * that becomes exact after the first Alpine render.
 */
final class TimezoneOptions
{
    private const CACHE_TTL = 3600;

    /**
     * Cache key fingerprint: changes when PHP or tzdata changes.
     */
    private static function cacheKey(): string
    {
        return 'tz-options-v1:'.hash('xxh128', PHP_VERSION.'|'.implode(',', DateTimeZone::listIdentifiers()));
    }

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
        return Cache::remember(self::cacheKey().':grouped', self::CACHE_TTL, function (): array {
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
        });
    }

    /**
     * Flat identifier => label list.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return Cache::remember(self::cacheKey().':options', self::CACHE_TTL, function (): array {
            $options = [];

            foreach (self::identifiers() as $identifier) {
                $options[$identifier] = self::label($identifier);
            }

            return $options;
        });
    }

    /**
     * Number of selectable identifiers, including UTC.
     */
    public static function count(): int
    {
        return count(self::identifiers());
    }

    /**
     * Search the full IANA catalogue and return grouped results.
     *
     * Underscores and spaces are treated as equivalent, so both the exact
     * identifier (America/Argentina/Buenos_Aires) and the readable city name
     * (Buenos Aires) match. The comparison is case-insensitive.
     *
     * @return array<string, array<string, string>>
     */
    public static function search(?string $query, int $limit = 200): array
    {
        $needle = self::normalizeForSearch((string) $query);

        if ($needle === '') {
            return self::grouped();
        }

        $results = [];
        $matches = 0;

        foreach (self::grouped() as $region => $zones) {
            foreach ($zones as $identifier => $label) {
                $haystack = self::normalizeForSearch($identifier.' '.$label);

                if (! str_contains($haystack, $needle)) {
                    continue;
                }

                $results[$region][$identifier] = $label;

                if (++$matches >= $limit) {
                    break 2;
                }
            }
        }

        return $results;
    }

    private static function normalizeForSearch(string $value): string
    {
        return trim(str_replace('_', ' ', mb_strtolower($value)));
    }

    public static function label(string $identifier): string
    {
        return sprintf('%s — UTC%s', $identifier, self::offset($identifier));
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
