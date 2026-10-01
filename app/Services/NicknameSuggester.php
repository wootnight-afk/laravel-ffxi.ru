<?php

namespace App\Services;

use App\Models\User;

class NicknameSuggester
{
    /**
     * @return array{
     *     available: bool,
     *     reason: ?string,
     *     suggestions: array<int, string>
     * }
     */
    public function check(string $name): array
    {
        if (! $this->passesFormat($name)) {
            return [
                'available' => false,
                'reason' => 'invalid',
                'suggestions' => [],
            ];
        }

        if ($this->isBlacklisted($name)) {
            return [
                'available' => false,
                'reason' => 'blacklisted',
                'suggestions' => [],
            ];
        }

        if ($this->isTaken($name)) {
            return [
                'available' => false,
                'reason' => 'taken',
                'suggestions' => $this->generateSuggestions($name),
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'suggestions' => [],
        ];
    }

    public function isAvailable(string $name): bool
    {
        return $this->passesFormat($name)
            && ! $this->isBlacklisted($name)
            && ! $this->isTaken($name);
    }

    public function passesFormat(string $name): bool
    {
        $min = (int) config('nickname.min', 3);
        $max = (int) config('nickname.max', 24);
        $regex = (string) config('nickname.regex', '/^[a-zA-Z][a-zA-Z0-9_-]*$/');

        $len = mb_strlen($name);

        return $len >= $min && $len <= $max && preg_match($regex, $name) === 1;
    }

    public function isBlacklisted(string $name): bool
    {
        $blacklist = config('nickname.blacklist', []);

        return in_array(mb_strtolower($name), $blacklist, true);
    }

    public function isTaken(string $name): bool
    {
        return User::query()->where('name', $name)->exists();
    }

    /**
     * @return array<int, string>
     */
    protected function generateSuggestions(string $base): array
    {
        $needed = (int) config('nickname.suggestions', 3);
        $minDigits = (int) config('nickname.suggestion_digit_min', 2);
        $maxDigits = (int) config('nickname.suggestion_digit_max', 4);
        $maxLen = (int) config('nickname.max', 24);

        $cleanBase = preg_replace('/[^a-zA-Z0-9_-]/', '', $base) ?? '';
        if ($cleanBase === '') {
            $cleanBase = 'user';
        }

        $candidates = [];
        $attempts = 0;
        $maxAttempts = $needed * 20;

        while (count($candidates) < $needed && $attempts < $maxAttempts) {
            $attempts++;

            $digits = random_int($minDigits, $maxDigits);
            $min = (int) ('1'.str_repeat('0', $digits - 1));
            $max = (int) str_repeat('9', $digits);
            $number = random_int($min, $max);
            $suffix = (string) $number;

            $baseLen = $maxLen - mb_strlen($suffix);
            $truncated = mb_substr($cleanBase, 0, max(1, $baseLen));
            $candidate = $truncated.$suffix;

            if (mb_strlen($candidate) < (int) config('nickname.min', 3)) {
                continue;
            }

            $candidates[$candidate] = true;
        }

        $candidates = array_keys($candidates);
        if (empty($candidates)) {
            return [];
        }

        $takenLower = User::query()
            ->whereIn('name', $candidates)
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();

        $blacklist = config('nickname.blacklist', []);

        $out = [];
        foreach ($candidates as $candidate) {
            if (in_array(mb_strtolower($candidate), $takenLower, true)) {
                continue;
            }
            if (in_array(mb_strtolower($candidate), $blacklist, true)) {
                continue;
            }
            $out[] = $candidate;
            if (count($out) >= $needed) {
                break;
            }
        }

        return $out;
    }
}
