<?php

namespace App\Enums;

/**
 * Playable races of Final Fantasy XI (ADR-013 §2.8).
 *
 * The value is the lowercase race name: it matches the values already stored
 * in `users.race`, so the legacy transfer needs no conversion. Race names are
 * in-game proper nouns and are not translated; only the "not specified"
 * caption is localised.
 */
enum CharacterRace: string
{
    case Hume = 'hume';
    case Elvaan = 'elvaan';
    case Tarutaru = 'tarutaru';
    case Mithra = 'mithra';
    case Galka = 'galka';

    public function label(): string
    {
        return match ($this) {
            self::Hume => 'Hume',
            self::Elvaan => 'Elvaan',
            self::Tarutaru => 'Tarutaru',
            self::Mithra => 'Mithra',
            self::Galka => 'Galka',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
