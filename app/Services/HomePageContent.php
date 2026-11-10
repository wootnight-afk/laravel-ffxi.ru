<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Locale;

/**
 * Single source of truth for the public home page (`/`).
 *
 * The home page is a singleton: there is no model and no table of its own,
 * the editable values live in the `settings` key/value store. A missing key
 * (or a key holding `null`) means "use the bundled default", so a fresh
 * installation renders exactly the same markup as before the editor existed.
 */
class HomePageContent implements PageContentProvider
{
    public const KEY_META_TITLE = 'home.meta_title';

    public const KEY_META_TITLE_EN = 'home.meta_title_en';

    public const KEY_META_DESCRIPTION = 'home.meta_description';

    public const KEY_META_DESCRIPTION_EN = 'home.meta_description_en';

    public const KEY_H1 = 'home.h1';

    public const KEY_H1_EN = 'home.h1_en';

    public const KEY_BODY = 'home.body';

    public const KEY_BODY_EN = 'home.body_en';

    public const KEY_HERO_LOGO = 'home.hero_logo';

    public const KEY_MAIN_IMAGE = 'home.main_image';

    public const KEY_MAIN_IMAGE_WIDTH = 'home.main_image_width';

    public const KEY_MAIN_IMAGE_HEIGHT = 'home.main_image_height';

    public const KEY_IS_MAIN_IMAGE_COMPONENT_ENABLED = 'home.is_main_image_component_enabled';

    public const KEY_IS_BODY_COMPONENT_ENABLED = 'home.is_body_component_enabled';

    /**
     * Bundled assets shipped with the repository. The hero crop in app.css is
     * derived from the alpha bounding box of this exact file, so it is used
     * verbatim unless an administrator uploads a replacement.
     */
    public const DEFAULT_HERO_LOGO = 'img/bx_ffxi_logo.webp';

    public const DEFAULT_MAIN_IMAGE = 'img/ffxi.webp';

    public const DEFAULT_META_TITLE = 'Содружество игроков Final Fantasy XI — Phoenix Server';

    public const DEFAULT_H1 = 'Final Fantasy XI — Community of Players';

    public const DEFAULT_MAIN_IMAGE_WIDTH = 592;

    public const DEFAULT_MAIN_IMAGE_HEIGHT = 333;

    /** @var array<int, string> */
    private const DEFAULT_PARAGRAPHS = [
        'Приветствую тебя, уважаемый искатель приключений! Ты оказался на страницах нашего сообщества игроков Final Fantasy XI – Phoenix Server. С радостью сообщаем, что этот сайт вновь распахнул свои двери после долгого перерыва! Несмотря на то, что некоторые русскоязычные игроки временно оставили волшебный мир Vana\'diel, есть и те, кто продолжает путешествовать по его бескрайним просторам. Для них Final Fantasy XI стала не просто игрой — это настоящая вселенная, где каждый может найти новых друзей, делиться впечатлениями, ставить амбициозные цели или просто наслаждаться коллекционированием миссий и уникального снаряжения.',
        'Эта игра превратилась в настоящую историю, полную незабываемых моментов и эпических свершений. Каждый из нас, кто хоть раз погружался в этот мир, может вспомнить те волнующие мгновения, с которых всё начиналось, и увидеть, как сильно он изменился. Присоединяйся к нам в этом увлекательном путешествии и дай новую жизнь своим приключениям в Final Fantasy XI.',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ContentRenderer $renderer,
    ) {}

    /**
     * Values used when a key is absent from the settings store.
     *
     * `null` for the image keys means "bundled asset", not "hidden block":
     * hiding the hero or the main image is not supported by design.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            self::KEY_META_TITLE => self::DEFAULT_META_TITLE,
            self::KEY_H1 => self::DEFAULT_H1,
            self::KEY_BODY => implode("\n\n", self::DEFAULT_PARAGRAPHS),
            self::KEY_HERO_LOGO => null,
            self::KEY_MAIN_IMAGE => null,
            self::KEY_MAIN_IMAGE_WIDTH => self::DEFAULT_MAIN_IMAGE_WIDTH,
            self::KEY_MAIN_IMAGE_HEIGHT => self::DEFAULT_MAIN_IMAGE_HEIGHT,
            self::KEY_IS_MAIN_IMAGE_COMPONENT_ENABLED => true,
            self::KEY_IS_BODY_COMPONENT_ENABLED => true,
        ];
    }

    /**
     * Current values shaped for the admin form.
     *
     * Custom image paths are absolute-relative paths on the `public` disk
     * (e.g. `home/hero/abc_hero.webp`); `null` means the bundled asset.
     *
     * @return array<string, mixed>
     */
    public function editable(): array
    {
        $defaults = $this->defaults();

        return [
            'meta_title' => $this->text(self::KEY_META_TITLE, $defaults[self::KEY_META_TITLE]),
            'meta_title_en' => $this->text(self::KEY_META_TITLE_EN, ''),
            'meta_description' => $this->text(self::KEY_META_DESCRIPTION, ''),
            'meta_description_en' => $this->text(self::KEY_META_DESCRIPTION_EN, ''),
            'h1' => $this->text(self::KEY_H1, $defaults[self::KEY_H1]),
            'h1_en' => $this->text(self::KEY_H1_EN, ''),
            'body' => $this->body($defaults[self::KEY_BODY]),
            'body_en' => $this->body('', self::KEY_BODY_EN),
            'hero_logo' => $this->customPath(self::KEY_HERO_LOGO),
            'main_image' => $this->customPath(self::KEY_MAIN_IMAGE),
            'reset_hero_logo' => false,
            'reset_main_image' => false,
            'is_main_image_component_enabled' => $this->componentEnabled(self::KEY_IS_MAIN_IMAGE_COMPONENT_ENABLED),
            'is_body_component_enabled' => $this->componentEnabled(self::KEY_IS_BODY_COMPONENT_ENABLED),
        ];
    }

    /**
     * Everything the `home` view needs, already resolved and escaped-safe.
     *
     * `bodyHtml` is produced by ContentRenderer (CommonMark + HtmlSanitizer),
     * so it may be printed unescaped.
     *
     * @return array{
     *     metaTitle: string,
     *     metaDescription: string,
     *     h1: string,
     *     heroLogoUrl: string,
     *     heroLogoIsCustom: bool,
     *     mainImageUrl: string,
     *     mainImageWidth: int,
     *     mainImageHeight: int,
     *     mainImageComponentEnabled: bool,
     *     bodyComponentEnabled: bool,
     *     bodyHtml: string
     * }
     */
    public function view(): array
    {
        $defaults = $this->defaults();

        $heroLogo = $this->customPath(self::KEY_HERO_LOGO);
        $mainImage = $this->customPath(self::KEY_MAIN_IMAGE);

        return [
            'metaTitle' => $this->localizedText(
                self::KEY_META_TITLE,
                $defaults[self::KEY_META_TITLE],
                self::KEY_META_TITLE_EN,
            ),
            'metaDescription' => $this->localizedText(
                self::KEY_META_DESCRIPTION,
                '',
                self::KEY_META_DESCRIPTION_EN,
            ),
            'h1' => $this->localizedText(
                self::KEY_H1,
                $defaults[self::KEY_H1],
                self::KEY_H1_EN,
            ),
            'heroLogoUrl' => $heroLogo !== null
                ? asset('storage/'.$heroLogo)
                : asset(self::DEFAULT_HERO_LOGO),
            'heroLogoIsCustom' => $heroLogo !== null,
            'mainImageUrl' => $mainImage !== null
                ? asset('storage/'.$mainImage)
                : asset(self::DEFAULT_MAIN_IMAGE),
            'mainImageWidth' => $this->dimension(self::KEY_MAIN_IMAGE_WIDTH, self::DEFAULT_MAIN_IMAGE_WIDTH),
            'mainImageHeight' => $this->dimension(self::KEY_MAIN_IMAGE_HEIGHT, self::DEFAULT_MAIN_IMAGE_HEIGHT),
            'mainImageComponentEnabled' => $this->componentEnabled(self::KEY_IS_MAIN_IMAGE_COMPONENT_ENABLED),
            'bodyComponentEnabled' => $this->componentEnabled(self::KEY_IS_BODY_COMPONENT_ENABLED),
            'bodyHtml' => $this->renderer->render($this->localizedBody(
                $defaults[self::KEY_BODY],
                self::KEY_BODY_EN,
            )),
        ];
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

    /**
     * Component switch stored in the settings store.
     *
     * A missing key means enabled, so existing installations keep rendering
     * the block until an administrator explicitly turns it off.
     */
    public function componentEnabled(string $key): bool
    {
        $value = $this->settings->get($key, true);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * Relative path of a custom uploaded image, or null for the bundled asset.
     */
    private function customPath(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function dimension(string $key, int $default): int
    {
        $value = $this->settings->get($key, $default);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
