<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\IpUtils;

class IpAllowlist implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('filament.settings.validation.ip_allowlist'));

            return;
        }

        foreach (preg_split('/\R/', $value) ?: [] as $entry) {
            $entry = trim($entry);

            if ($entry !== '' && ! self::isValidEntry($entry)) {
                $fail(__('filament.settings.validation.ip_allowlist'));

                return;
            }
        }
    }

    public static function isValidEntry(string $entry): bool
    {
        $parts = explode('/', $entry);

        if (count($parts) > 2) {
            return false;
        }

        $ip = $parts[0];

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (isset($parts[1])) {
            $maxPrefix = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

            if (! ctype_digit($parts[1]) || (int) $parts[1] > $maxPrefix) {
                return false;
            }
        }

        return IpUtils::checkIp($ip, $entry);
    }
}
