<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case Ru = 'ru';
    case En = 'en';

    /**
     * Supported locale codes.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::cases());
    }

    /**
     * Locale code => native label, for profile and admin selects.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $locale) {
            $options[$locale->value] = $locale->label();
        }

        return $options;
    }

    public function label(): string
    {
        return (string) __('site.locale.'.$this->value);
    }
}
