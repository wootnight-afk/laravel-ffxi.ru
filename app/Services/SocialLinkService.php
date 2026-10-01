<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSocialLink;
use Illuminate\Validation\ValidationException;

class SocialLinkService
{
    /**
     * @return array<int, string>
     */
    public function allowedPlatforms(): array
    {
        return array_keys(config('social.platforms', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function platform(string $key): array
    {
        return config("social.platforms.{$key}", []);
    }

    /**
     * Формирует и валидирует URL из имени платформы + username или готового URL.
     *
     * @throws ValidationException
     */
    public function buildUrl(string $type, ?string $username, ?string $rawUrl): string
    {
        $platform = $this->platform($type);
        $template = $platform['url_template'] ?? null;

        if ($template && $username) {
            $url = str_replace('{username}', $username, $template);
        } elseif ($rawUrl) {
            $url = $rawUrl;
        } else {
            throw ValidationException::withMessages([
                'url' => 'Укажите URL или username.',
            ]);
        }

        $this->validateUrl($url);

        return $url;
    }

    /**
     * @throws ValidationException
     */
    public function validateUrl(string $url): void
    {
        if (mb_strlen($url) > 500) {
            throw ValidationException::withMessages([
                'url' => 'Ссылка слишком длинная (макс. 500).',
            ]);
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array(strtolower((string) $scheme), ['https'], true)) {
            throw ValidationException::withMessages([
                'url' => 'Разрешены только ссылки с https://',
            ]);
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw ValidationException::withMessages([
                'url' => 'Некорректный URL.',
            ]);
        }

        // Явно запрещаем опасные схемы и obfuscation
        $lower = strtolower($url);
        foreach (['javascript:', 'data:', 'file:', 'vbscript:'] as $bad) {
            if (str_contains($lower, $bad)) {
                throw ValidationException::withMessages([
                    'url' => 'Недопустимая схема ссылки.',
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureLimit(User $user): void
    {
        $limit = (int) config('social.max_links_per_user', 10);
        $count = $user->socialLinks()->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'url' => "Достигнут лимит: {$limit} ссылок.",
            ]);
        }
    }

    public function add(User $user, string $type, ?string $username, ?string $rawUrl, ?string $label): UserSocialLink
    {
        $this->ensureLimit($user);

        $url = $this->buildUrl($type, $username, $rawUrl);

        $sortOrder = (int) $user->socialLinks()->max('sort_order') + 1;

        return UserSocialLink::create([
            'user_id' => $user->id,
            'type' => $type,
            'label' => $label,
            'username' => $username,
            'url' => $url,
            'is_visible' => false,
            'sort_order' => $sortOrder,
        ]);
    }
}
