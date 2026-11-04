<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Single source of truth for the static header of the public news page
 * (`/news`).
 *
 * The news list itself (query, loop, pagination) is not configurable and is
 * rendered by `resources/views/news/index.blade.php` directly from the
 * paginator. Only the header above the divider is editable: meta title, H1,
 * header image and the introductory text.
 *
 * Values live in the `settings` key/value store, so there is no model and no
 * table of its own. A missing key (or a key holding `null`) means "use the
 * bundled default", which keeps a fresh installation identical to the previous
 * hard-coded page.
 */
class NewsPageContent
{
    public const KEY_META_TITLE = 'news_page.meta_title';

    public const KEY_H1 = 'news_page.h1';

    public const KEY_BODY = 'news_page.body';

    public const KEY_MAIN_IMAGE = 'news_page.main_image';

    public const KEY_MAIN_IMAGE_WIDTH = 'news_page.main_image_width';

    public const KEY_MAIN_IMAGE_HEIGHT = 'news_page.main_image_height';

    public const DEFAULT_MAIN_IMAGE = 'img/ogp_eu.webp';

    public const DEFAULT_META_TITLE = 'Новости игры';

    public const DEFAULT_H1 = 'Новости игры';

    /**
     * The header image is key art rather than a page-specific picture, so the
     * alt text is fixed instead of being editable. Keeping it constant also
     * preserves the exact markup of the page that existed before the editor.
     */
    public const MAIN_IMAGE_ALT = 'Final Fantasy XI — MMORPG Square Enix';

    public const DEFAULT_MAIN_IMAGE_WIDTH = 1200;

    public const DEFAULT_MAIN_IMAGE_HEIGHT = 630;

    /** @var array<int, string> */
    private const DEFAULT_PARAGRAPHS = [
        'Final Fantasy XI, запущенная в 2002 году, на протяжении многих лет радовала своих преданных фанатов, даже после завершения сюжетной линии. Однако, с течением времени разработчики Square Enix столкнулись с непростым выбором: что же делать с этой культовой MMORPG? Варианты варьировались от режима «технического обслуживания» и прекращения поддержки до полного отключения серверов. Однако Фанаты Final Fantasy XI вновь обратились к Square Enix с просьбой о долгосрочной поддержке своей любимой игры. Их страсть и преданность не остались незамеченными: компания подтвердила, что MMORPG продолжит развиваться! Игроков ждут улучшения качества жизни, новые высокоуровневые задания и захватывающие испытания, а также расширение мира Ванаʼдиэля. Приготовьтесь к новым приключениям в этом удивительном мире!',
        "MMORPG Final Fantasy XI, вышедшая более 20 лет назад, продолжит получать обновления.\nКак стало известно из интервью геймдиректора Ёдзи Фудзито для Dengeki Online, в 2022 году в Square Enix всерьёз обсуждали завершение поддержки проекта. Рассматривалась даже возможность отключения серверов ПК-версии.\nПричиной для таких обсуждений должен был стать финал сюжетной линии The Voracious Resurgence, после которого в компании ожидали снижение интереса к игре. Однако прогнозы не оправдались: сообщество продолжило активно играть и оформлять подписку, стоимость которой с 2002 года остаётся неизменной — $11,95 в месяц.\nВ результате было принято решение не только сохранить поддержку, но и провести модернизацию сетевой инфраструктуры и реструктуризацию команды разработчиков. В ближайших обновлениях в Final Fantasy XI появятся смена расы персонажа, доработки для комфортного одиночного прохождения, улучшения в управлении, переработка боевой системы Limbus и другие геймплейные изменения.",
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ContentRenderer $renderer,
    ) {}

    /**
     * Values used when a key is absent from the settings store.
     *
     * `null` for the image key means "bundled asset", not "hidden block":
     * hiding the header image is not supported by design.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            self::KEY_META_TITLE => self::DEFAULT_META_TITLE,
            self::KEY_H1 => self::DEFAULT_H1,
            self::KEY_BODY => implode("\n\n", self::DEFAULT_PARAGRAPHS),
            self::KEY_MAIN_IMAGE => null,
            self::KEY_MAIN_IMAGE_WIDTH => self::DEFAULT_MAIN_IMAGE_WIDTH,
            self::KEY_MAIN_IMAGE_HEIGHT => self::DEFAULT_MAIN_IMAGE_HEIGHT,
        ];
    }

    /**
     * Current values shaped for the admin form.
     *
     * A custom image path is relative to the `public` disk (e.g.
     * `news/header/abc_header.webp`); `null` means the bundled asset.
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
            'main_image' => $this->customPath(self::KEY_MAIN_IMAGE),
            'reset_main_image' => false,
        ];
    }

    /**
     * Everything the `news/index` view needs, already resolved.
     *
     * `bodyHtml` is produced by ContentRenderer (CommonMark + HtmlSanitizer),
     * so it may be printed unescaped.
     *
     * @return array{
     *     metaTitle: string,
     *     h1: string,
     *     mainImageUrl: string,
     *     mainImageAlt: string,
     *     mainImageWidth: int,
     *     mainImageHeight: int,
     *     bodyHtml: string
     * }
     */
    public function view(): array
    {
        $defaults = $this->defaults();

        $mainImage = $this->customPath(self::KEY_MAIN_IMAGE);

        return [
            'metaTitle' => $this->text(self::KEY_META_TITLE, $defaults[self::KEY_META_TITLE]),
            'h1' => $this->text(self::KEY_H1, $defaults[self::KEY_H1]),
            'mainImageUrl' => $mainImage !== null
                ? asset('storage/'.$mainImage)
                : asset(self::DEFAULT_MAIN_IMAGE),
            'mainImageAlt' => self::MAIN_IMAGE_ALT,
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
