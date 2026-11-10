<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Locale;

/**
 * Single source of truth for the static header of the public gallery page
 * (`/gallery`).
 *
 * The album list itself (query, loop) is not configurable here: the editor
 * manages the meta title, H1 and introductory text above the albums, plus the
 * administrator-only switch that hides the album list on the front end.
 *
 * Values live in the `settings` key/value store, so there is no model and no
 * table of its own. A missing key (or a key holding `null`) means "use the
 * bundled default", which keeps a fresh installation identical to the previous
 * hard-coded page.
 */
class GalleryPageContent implements PageContentProvider
{
    public const KEY_META_TITLE = 'gallery_page.meta_title';

    public const KEY_META_TITLE_EN = 'gallery_page.meta_title_en';

    public const KEY_META_DESCRIPTION = 'gallery_page.meta_description';

    public const KEY_META_DESCRIPTION_EN = 'gallery_page.meta_description_en';

    public const KEY_H1 = 'gallery_page.h1';

    public const KEY_H1_EN = 'gallery_page.h1_en';

    public const KEY_BODY = 'gallery_page.body';

    public const KEY_BODY_EN = 'gallery_page.body_en';

    public const KEY_IS_GALLERY_COMPONENT_ENABLED = 'gallery_page.is_gallery_component_enabled';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ContentRenderer $renderer,
    ) {}

    /**
     * Values used when a key is absent from the settings store.
     *
     * The default title is the localized site label, so the English page keeps
     * showing "Gallery" until an administrator provides a custom value.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            self::KEY_META_TITLE => __('site.gallery.title'),
            self::KEY_H1 => __('site.gallery.title'),
            self::KEY_BODY => '',
            self::KEY_IS_GALLERY_COMPONENT_ENABLED => true,
        ];
    }

    /**
     * Current values shaped for the admin form.
     *
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
            'is_gallery_component_enabled' => $this->componentEnabled(),
        ];
    }

    /**
     * Everything the `gallery/index` view needs, already resolved.
     *
     * `bodyHtml` is produced by ContentRenderer (CommonMark + HtmlSanitizer),
     * so it may be printed unescaped.
     *
     * @return array{
     *     metaTitle: string,
     *     metaDescription: string,
     *     h1: string,
     *     galleryComponentEnabled: bool,
     *     bodyHtml: string
     * }
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
            'galleryComponentEnabled' => $this->componentEnabled(),
            'bodyHtml' => $this->renderer->render($this->localizedBody(
                (string) $defaults[self::KEY_BODY],
                self::KEY_BODY_EN,
            )),
        ];
    }

    /**
     * Whether the album list is rendered on `/gallery`.
     *
     * A missing key means enabled, so existing installations keep the list.
     */
    public function componentEnabled(): bool
    {
        $value = $this->settings->get(self::KEY_IS_GALLERY_COMPONENT_ENABLED, true);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * Non-blank text setting; blank values fall back to the default.
     */
    private function text(string $key, string $default): string
    {
        $value = $this->settings->get($key, $default);

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    /**
     * Page body is allowed to be empty: an empty string hides the block on the
     * front end, while a missing key keeps the default text.
     */
    private function body(string $default, string $key = self::KEY_BODY): string
    {
        $value = $this->settings->get($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * Per-field fallback for the English locale (ADR-011 §2.20–2.21):
     * a non-empty EN value wins, otherwise the RU value (or its default).
     */
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
