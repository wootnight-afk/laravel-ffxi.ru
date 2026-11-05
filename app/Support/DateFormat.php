<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\TimezoneOptions;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Rendering helpers for the three time semantics (ADR-011 §2.5, §2.10).
 *
 * Calendar dates are formatted in the timezone they carry and never shifted.
 * Moments are converted to the viewer timezone by the caller (the Blade
 * components and models resolve it through TimezoneResolver). System
 * timestamps are always rendered in UTC.
 */
final class DateFormat
{
    public const PLACEHOLDER = '—';

    /**
     * Calendar date without any timezone conversion (RU `04.11.2026`,
     * EN `Nov 04, 2026`).
     */
    public static function date(?CarbonInterface $value, ?string $locale = null): string
    {
        if ($value === null) {
            return self::PLACEHOLDER;
        }

        return $value->format(self::dateFormat($locale));
    }

    /**
     * Moment in the given timezone (date + time by default).
     *
     * @param  string  $format  `datetime`, `date` or `time`
     */
    public static function moment(
        ?CarbonInterface $value,
        ?string $timezone = null,
        ?string $locale = null,
        string $format = 'datetime',
        bool $withTimezone = false,
    ): string {
        if ($value === null) {
            return self::PLACEHOLDER;
        }

        $timezone ??= 'UTC';
        $local = $value->copy()->setTimezone($timezone);

        $text = match ($format) {
            'date' => self::date($local, $locale),
            'time' => $local->format('H:i'),
            default => $local->format(self::dateFormat($locale).' H:i'),
        };

        return $withTimezone ? $text.' ('.$timezone.')' : $text;
    }

    /**
     * System timestamp: always UTC with an explicit `(UTC)` marker.
     *
     * @param  string  $format  `datetime`, `date` or `time`
     */
    public static function utc(?CarbonInterface $value, ?string $locale = null, string $format = 'datetime'): string
    {
        $text = self::moment($value, 'UTC', $locale, $format);

        return $text === self::PLACEHOLDER ? $text : $text.' (UTC)';
    }

    /**
     * Local time of an event for the viewer, e.g. `21:00 Europe/Moscow`.
     *
     * The date is included only when the local date differs from the UTC date,
     * so a midnight transition is never hidden. Returns null when no usable
     * viewer timezone is known (UTC is the international baseline and is
     * already rendered by the UTC component).
     */
    public static function eventLocal(
        ?CarbonInterface $value,
        ?string $timezone = null,
        ?string $locale = null,
    ): ?string {
        if ($value === null || $timezone === null || $timezone === 'UTC' || ! TimezoneOptions::isValid($timezone)) {
            return null;
        }

        $local = $value->copy()->setTimezone($timezone);
        $utcDate = $value->copy()->setTimezone('UTC')->toDateString();

        $format = $local->toDateString() === $utcDate
            ? 'H:i'
            : self::dateFormat($locale).' H:i';

        return $local->format($format).' '.$timezone;
    }

    /**
     * Chat divider label based on the viewer's local date.
     */
    public static function localDayLabel(
        ?CarbonInterface $value,
        ?string $timezone = null,
        ?string $locale = null,
    ): string {
        if ($value === null) {
            return self::PLACEHOLDER;
        }

        $local = $value->copy()->setTimezone($timezone ?? 'UTC');

        if ($local->isToday()) {
            return 'Сегодня';
        }

        if ($local->isYesterday()) {
            return 'Вчера';
        }

        return self::date($local, $locale);
    }

    /**
     * Machine-readable ISO 8601 value for the `<time>` element.
     */
    public static function iso(?CarbonInterface $value, ?string $timezone = null): ?string
    {
        return $value?->copy()->setTimezone($timezone ?? 'UTC')->toIso8601String();
    }

    /**
     * Accepts Carbon, any DateTimeInterface or a parseable string; invalid
     * values render as the placeholder instead of raising an exception.
     */
    public static function toCarbon(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function dateFormat(?string $locale): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? 'M d, Y' : 'd.m.Y';
    }
}
