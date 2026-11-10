<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Locale;

/**
 * Single source of truth for the static header of the public events page
 * (`/events`).
 *
 * Values live in the `settings` key/value store. A missing key means "use the
 * bundled default" (the site.lang value).
 */
class EventsPageContent implements PageContentProvider
{
    public const KEY_META_TITLE = 'events.meta_title';

    public const KEY_META_TITLE_EN = 'events.meta_title_en';

    public const KEY_META_DESCRIPTION = 'events.meta_description';

    public const KEY_META_DESCRIPTION_EN = 'events.meta_description_en';

    public const KEY_H1 = 'events.h1';

    public const KEY_H1_EN = 'events.h1_en';

    public const KEY_BODY = 'events.body';

    public const KEY_BODY_EN = 'events.body_en';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ContentRenderer $renderer,
    ) {}

    /**
     * Values used when a key is absent from the settings store.
     *
     * The default title is the localized site label, so the English page keeps
     * showing "Events" until an administrator provides a custom value.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $title = __('site.events.title');

        return [
            self::KEY_META_TITLE => $title,
            self::KEY_H1 => $title,
            self::KEY_BODY => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editable(): array
    {
        $defaults = $this->defaults();

        return [
            'meta_title' => $this->text(self::KEY_META_TITLE, (string) $defaults[self::KEY_META_TITLE]),
            'meta_title_en' => $this->text(self::KEY_META_TITLE_EN, ''),
            'meta_description' => $this->text(self::KEY_META_DESCRIPTION, ''),
            'meta_description_en' => $this->text(self::KEY_META_DESCRIPTION_EN, ''),
            'h1' => $this->text(self::KEY_H1, (string) $defaults[self::KEY_H1]),
            'h1_en' => $this->text(self::KEY_H1_EN, ''),
            'body' => $this->body((string) $defaults[self::KEY_BODY]),
            'body_en' => $this->body('', self::KEY_BODY_EN),
        ];
    }

    /**
     * @return array{metaTitle: string, metaDescription: string, h1: string, bodyHtml: string}
     */
    public function view(): array
    {
        $defaults = $this->defaults();

        return [
            'metaTitle' => $this->localizedText(
                self::KEY_META_TITLE,
                (string) $defaults[self::KEY_META_TITLE],
                self::KEY_META_TITLE_EN,
            ),
            'metaDescription' => $this->localizedText(
                self::KEY_META_DESCRIPTION,
                '',
                self::KEY_META_DESCRIPTION_EN,
            ),
            'h1' => $this->localizedText(
                self::KEY_H1,
                (string) $defaults[self::KEY_H1],
                self::KEY_H1_EN,
            ),
            'bodyHtml' => $this->renderer->render($this->localizedBody(
                (string) $defaults[self::KEY_BODY],
                self::KEY_BODY_EN,
            )),
        ];
    }

    private function text(string $key, string $default): string
    {
        $value = $this->settings->get($key, $default);

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    private function body(string $default, string $key = self::KEY_BODY): string
    {
        $value = $this->settings->get($key, $default);

        return is_string($value) ? $value : $default;
    }

    private function localizedText(string $key, string $default, string $englishKey): string
    {
        if (app()->getLocale() === Locale::En->value) {
            $value = $this->settings->get($englishKey);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $this->text($key, $default);
    }

    private function localizedBody(string $default, string $englishKey): string
    {
        if (app()->getLocale() === Locale::En->value) {
            $value = $this->settings->get($englishKey);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $this->body($default);
    }
}
