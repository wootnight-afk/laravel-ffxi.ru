<?php

namespace App\Services;

use App\Models\GameCharacter;
use App\Models\User;
use BackedEnum;

/**
 * Normalisation and hashing rules of the legacy game data transfer
 * (STAGE-1 §6.3.1).
 *
 * Two independent quantities:
 *  - the normalised source hash, used only to match the account value;
 *  - the control hash of the value actually stored, used by the rollback to
 *    detect any manual edit, including `NULL` → `''`, a case change or an
 *    added space — exactly the differences normalisation is meant to hide.
 */
class LegacyGameDataValues
{
    /** @var array<int, string> */
    public const FIELDS = ['race', 'main_job', 'legend'];

    /**
     * @return array<string, string>
     */
    public function legacyValues(User $user): array
    {
        $values = [];

        foreach (self::FIELDS as $field) {
            $normalized = $this->normalized($field, $user->{$field});

            if ($normalized !== null) {
                $values[$field] = $normalized;
            }
        }

        return $values;
    }

    /**
     * Normalised form used for import comparison and for "value is absent".
     */
    public function normalized(string $field, mixed $value): ?string
    {
        $text = $this->scalar($value);

        if ($text === null) {
            return null;
        }

        $text = match ($field) {
            'legend' => str_replace(["\r\n", "\r"], "\n", $text),
            'race' => mb_strtolower(trim($text)),
            'main_job' => mb_strtoupper(trim($text)),
            default => trim($text),
        };

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    public function scalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return (string) $value;
    }

    /**
     * Control hash of the value as it is stored: no trim, no case folding and
     * `NULL` is distinct from an empty string.
     */
    public function controlHash(string $field, mixed $value, ?string $job = null): string
    {
        if ($value === null) {
            return hash('sha256', $field."\0null\0".($job ?? ''));
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_int($value) || $field === 'jobs') {
            return hash('sha256', $field."\0int\0".($job ?? '')."\0".(int) $value);
        }

        return hash('sha256', $field."\0str\0".($job ?? '')."\0".(string) $value);
    }

    public function sourceHash(string $field, string $normalized): string
    {
        return hash('sha256', $field."\0norm\0".$normalized);
    }

    public function currentValue(GameCharacter $character, string $field): mixed
    {
        return match ($field) {
            'race', 'main_job', 'legend', 'legend_html',
            'legend_ru', 'legend_ru_html', 'legend_en', 'legend_en_html' => $character->{$field},
            default => null,
        };
    }
}
