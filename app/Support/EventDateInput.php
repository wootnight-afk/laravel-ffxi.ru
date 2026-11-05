<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\TimezoneOptions;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts a naive local event time into a UTC moment (ADR-011 §2.7).
 *
 * PHP silently normalizes non-existent local times (spring forward) and picks
 * an arbitrary offset for ambiguous ones (fall back). Both cases are detected
 * explicitly here so the caller can reject the input instead of storing an
 * unintended moment.
 */
final class EventDateInput
{
    public const ERROR_NON_EXISTENT = 'non_existent';

    public const ERROR_AMBIGUOUS = 'ambiguous';

    /**
     * Normalizes supported input formats to `Y-m-d H:i`.
     *
     * @return string|null null when the value is not a recognized local date/time
     */
    public static function normalize(string $value): ?string
    {
        foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $dateTime = DateTimeImmutable::createFromFormat('!'.$format, $value, new DateTimeZone('UTC'));

            if ($dateTime === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            if ($dateTime->format($format) !== $value) {
                continue;
            }

            return $dateTime->format('Y-m-d H:i');
        }

        return null;
    }

    /**
     * @return array{0: string|null, 1: string|null} UTC value and error code
     */
    public static function toUtc(string $value, string $timezone): array
    {
        $local = self::normalize($value);

        if ($local === null) {
            return [null, null];
        }

        if (! TimezoneOptions::isValid($timezone)) {
            return [null, null];
        }

        $zone = new DateTimeZone($timezone);
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $local, $zone);

        if ($dateTime === false) {
            return [null, null];
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return [null, null];
        }

        // Spring forward: PHP moves the non-existent time to a later value.
        if ($dateTime->format('Y-m-d H:i') !== $local) {
            return [null, self::ERROR_NON_EXISTENT];
        }

        // Fall back: the same local time exists twice; PHP picked one offset.
        if (self::isAmbiguous($dateTime, $local, $zone)) {
            return [null, self::ERROR_AMBIGUOUS];
        }

        return [
            $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            null,
        ];
    }

    public static function message(string $error): string
    {
        return match ($error) {
            self::ERROR_NON_EXISTENT => 'Указанное время не существует в выбранном часовом поясе (переход на летнее время). Выберите другое время или другой пояс',
            self::ERROR_AMBIGUOUS => 'Указанное время неоднозначно в выбранном часовом поясе (переход на зимнее время). Укажите время после 03:00 или другой часовой пояс',
            default => 'Некорректное время события.',
        };
    }

    private static function isAmbiguous(DateTimeImmutable $dateTime, string $local, DateTimeZone $timezone): bool
    {
        $timestamp = $dateTime->getTimestamp();
        $transitions = $timezone->getTransitions($timestamp - 172800, $timestamp + 172800);

        if (count($transitions) < 2) {
            return false;
        }

        $localTimestamp = (new DateTimeImmutable($local, new DateTimeZone('UTC')))->getTimestamp();

        for ($index = 1; $index < count($transitions); $index++) {
            $before = $transitions[$index - 1];
            $after = $transitions[$index];

            // Only a backward transition creates a repeated local interval.
            if ($after['offset'] >= $before['offset']) {
                continue;
            }

            $ambiguousStart = $after['ts'] + $after['offset'];
            $ambiguousEnd = $after['ts'] + $before['offset'];

            if ($localTimestamp >= $ambiguousStart && $localTimestamp < $ambiguousEnd) {
                return true;
            }
        }

        return false;
    }
}
