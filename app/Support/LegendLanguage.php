<?php

namespace App\Support;

/**
 * Deterministic language detection for a stored legend (STAGE-1 §16.2).
 *
 * The goal is not linguistic accuracy but a safe split of the texts that
 * already exist: a text is assigned to RU or EN only when one script clearly
 * dominates the other. Everything else is reported as ambiguous and kept in
 * the legacy column for the owner to review, so a wrong guess never hides or
 * rewrites the original.
 */
final class LegendLanguage
{
    public const RU = 'ru';

    public const EN = 'en';

    public const AMBIGUOUS = 'ambiguous';

    /**
     * Share of the dominant script required to make a decision.
     */
    private const THRESHOLD = 0.6;

    public static function detect(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return self::AMBIGUOUS;
        }

        $cyrillic = (int) preg_match_all('/\p{Cyrillic}/u', $text);
        $latin = (int) preg_match_all('/\p{Latin}/u', $text);
        $letters = $cyrillic + $latin;

        if ($letters === 0) {
            return self::AMBIGUOUS;
        }

        if ($cyrillic / $letters >= self::THRESHOLD) {
            return self::RU;
        }

        if ($latin / $letters >= self::THRESHOLD) {
            return self::EN;
        }

        return self::AMBIGUOUS;
    }

    /**
     * Locale that receives a text whose language could not be determined.
     *
     * Nothing is translated: the original text is stored unchanged in the
     * primary site locale so it stays visible, and the record is listed in the
     * report for a manual correction.
     */
    public static function fallbackLocale(): string
    {
        return self::RU;
    }
}
