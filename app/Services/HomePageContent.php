<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Single source of truth for the public home page (`/`).
 *
 * The home page is a singleton: there is no model and no table of its own,
 * the editable values live in the `settings` key/value store. A missing key
 * (or a key holding `null`) means "use the bundled default", so a fresh
 * installation renders exactly the same markup as before the editor existed.
 */
class HomePageContent
{
    public const KEY_META_TITLE = 'home.meta_title';

    public const KEY_H1 = 'home.h1';

    public const KEY_BODY = 'home.body';

    public const KEY_HERO_LOGO = 'home.hero_logo';

    public const KEY_MAIN_IMAGE = 'home.main_image';

    public const KEY_MAIN_IMAGE_WIDTH = 'home.main_image_width';

    public const KEY_MAIN_IMAGE_HEIGHT = 'home.main_image_height';

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
            'h1' => $this->text(self::KEY_H1, $defaults[self::KEY_H1]),
            'body' => $this->body($defaults[self::KEY_BODY]),
            'hero_logo' => $this->customPath(self::KEY_HERO_LOGO),
            'main_image' => $this->customPath(self::KEY_MAIN_IMAGE),
            'reset_hero_logo' => false,
            'reset_main_image' => false,
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
     *     h1: string,
     *     heroLogoUrl: string,
     *     heroLogoIsCustom: bool,
     *     mainImageUrl: string,
     *     mainImageWidth: int,
     *     mainImageHeight: int,
     *     bodyHtml: string
     * }
     */
    public function view(): array
    {
        $defaults = $this->defaults();

        $heroLogo = $this->customPath(self::KEY_HERO_LOGO);
        $mainImage = $this->customPath(self::KEY_MAIN_IMAGE);

        return [
            'metaTitle' => $this->text(self::KEY_META_TITLE, $defaults[self::KEY_META_TITLE]),
            'h1' => $this->text(self::KEY_H1, $defaults[self::KEY_H1]),
            'heroLogoUrl' => $heroLogo !== null
                ? asset('storage/'.$heroLogo)
                : asset(self::DEFAULT_HERO_LOGO),
            'heroLogoIsCustom' => $heroLogo !== null,
            'mainImageUrl' => $mainImage !== null
                ? asset('storage/'.$mainImage)
                : asset(self::DEFAULT_MAIN_IMAGE),
            'mainImageWidth' => $this->dimension(self::KEY_MAIN_IMAGE_WIDTH, self::DEFAULT_MAIN_IMAGE_WIDTH),
            'mainImageHeight' => $this->dimension(self::KEY_MAIN_IMAGE_HEIGHT, self::DEFAULT_MAIN_IMAGE_HEIGHT),
            'bodyHtml' => $this->renderer->render($this->body($defaults[self::KEY_BODY])),
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
    private function body(string $default): string
    {
        $value = $this->settings->get(self::KEY_BODY, $default);

        return is_string($value) ? $value : $default;
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
