<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Locale;
use App\Http\Controllers\PageController;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

/**
 * CMS navigation of the public header and footer (ADR-011 §2.22–2.23).
 *
 * Items live in the `settings` key/value store as JSON arrays. The system
 * controls of the header (language/theme switchers, activity bell, profile,
 * sign-in/out) are rendered by the layout and are deliberately not part of
 * this structure.
 */
final class SiteNavigation
{
    public const KEY_HEADER_ITEMS = 'header.items';

    public const KEY_FOOTER_ITEMS = 'footer.items';

    public const KEY_FOOTER_TEXT_RU = 'footer.text_ru';

    public const KEY_FOOTER_TEXT_EN = 'footer.text_en';

    public const TYPE_ROUTE = 'route';

    public const TYPE_PAGE = 'page';

    public const TYPE_URL = 'url';

    /**
     * System routes that may be used as navigation targets.
     *
     * @var array<int, string>
     */
    public const ROUTES = [
        'home',
        'news.index',
        'gallery.index',
        'players.dashboard',
        'contacts',
        'events.index',
        'login',
        'register',
    ];

    public const DEFAULT_FOOTER_TEXT = 'Community of Players Final Fantasy XI - Phoenix Server';

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function defaultHeaderItems(): array
    {
        return [
            $this->item('news', 'Новости игры', 'Game news', self::TYPE_ROUTE, 'news.index'),
            $this->item('gallery', 'Галерея', 'Gallery', self::TYPE_ROUTE, 'gallery.index'),
            $this->item('players', 'Игроки', 'Players', self::TYPE_ROUTE, 'players.dashboard'),
            $this->item('contacts', 'Контакты', 'Contacts', self::TYPE_ROUTE, 'contacts'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function defaultFooterItems(): array
    {
        return [
            $this->item('contacts', 'Контакты', 'Contacts', self::TYPE_ROUTE, 'contacts'),
            $this->item('login', 'Авторизация', 'Log in', self::TYPE_ROUTE, 'login'),
        ];
    }

    /**
     * Raw items for the admin repeater.
     *
     * @return array<int, array<string, mixed>>
     */
    public function editableHeader(): array
    {
        $items = $this->storedItems(self::KEY_HEADER_ITEMS, $this->defaultHeaderItems());
        if (! array_any($items, fn (array $item): bool => $this->isHome($item))) {
            array_unshift($items, $this->item('home-logo', 'Главная', 'Home', self::TYPE_ROUTE, 'home'));
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function editableFooter(): array
    {
        return $this->storedItems(self::KEY_FOOTER_ITEMS, $this->defaultFooterItems());
    }

    /**
     * Resolved header items for the current locale.
     *
     * @return array<int, array{label: string, url: string, imageUrl: ?string, active: bool}>
     */
    public function headerItems(): array
    {
        $items = array_filter($this->editableHeader(), fn (array $item): bool => ! $this->isHome($item));

        return $this->resolveItems(array_values($items), 'show_in_menu');
    }

    /**
     * @return array<int, array{label: string, url: string, imageUrl: ?string, active: bool}>
     */
    public function footerItems(): array
    {
        return $this->resolveItems($this->editableFooter(), 'show_in_footer');
    }

    public function footerText(): string
    {
        $ru = $this->settings->get(self::KEY_FOOTER_TEXT_RU);

        if (app()->getLocale() !== Locale::En->value) {
            return is_string($ru) && trim($ru) !== '' ? $ru : self::DEFAULT_FOOTER_TEXT;
        }

        $en = $this->settings->get(self::KEY_FOOTER_TEXT_EN);

        if (is_string($en) && trim($en) !== '') {
            return $en;
        }

        // Per-field fallback: an empty English text uses the Russian one.
        return is_string($ru) && trim($ru) !== '' ? $ru : self::DEFAULT_FOOTER_TEXT;
    }

    /**
     * @return array{text_ru: string, text_en: string}
     */
    public function editableFooterText(): array
    {
        $ru = $this->settings->get(self::KEY_FOOTER_TEXT_RU);
        $en = $this->settings->get(self::KEY_FOOTER_TEXT_EN);

        return [
            'text_ru' => is_string($ru) && trim($ru) !== '' ? $ru : self::DEFAULT_FOOTER_TEXT,
            'text_en' => is_string($en) && trim($en) !== '' ? $en : self::DEFAULT_FOOTER_TEXT,
        ];
    }

    /**
     * Whether a system route is currently shown in the header and footer.
     *
     * Used by the unified page editor to prefill the menu toggles of a system
     * page. A missing item means "not shown"; the home item is treated as a
     * header item because the logo link is always rendered by the layout.
     *
     * @return array{menu: bool, footer: bool}
     */
    public function routeVisibility(string $route): array
    {
        return [
            'menu' => $this->routeItemVisible($this->editableHeader(), $route),
            'footer' => $this->routeItemVisible($this->editableFooter(), $route),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function routeItemVisible(array $items, string $route): bool
    {
        foreach ($items as $item) {
            if (($item['type'] ?? null) === self::TYPE_ROUTE && ($item['target'] ?? null) === $route) {
                return (bool) ($item['visible'] ?? true);
            }
        }

        return false;
    }

    public function homeLinkVisible(): bool
    {
        foreach ($this->editableHeader() as $item) {
            if ($this->isHome($item)) {
                return (bool) ($item['visible'] ?? true);
            }
        }

        return true;
    }

    /** @param array<string, mixed> $item */
    private function isHome(array $item): bool
    {
        return ($item['type'] ?? null) === self::TYPE_ROUTE && ($item['target'] ?? null) === 'home';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{label: string, url: string, imageUrl: ?string, active: bool}>
     */
    private function resolveItems(array $items, string $pageFlag): array
    {
        // The stored `order` is authoritative; array order is the fallback.
        usort($items, fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        $resolved = [];

        foreach ($items as $item) {
            $page = null;
            if (($item['type'] ?? null) === self::TYPE_PAGE) {
                $target = (string) ($item['target'] ?? '');
                $page = ctype_digit($target) ? Page::find((int) $target) : null;
                if ($page === null || ! $page->is_published || ! $page->getAttribute($pageFlag)
                    || in_array($page->slug, PageController::RESERVED_SLUGS, true)) {
                    continue;
                }
            } elseif (! ($item['visible'] ?? true)) {
                continue;
            }

            $url = $page !== null && Route::has('page.show')
                ? route('page.show', ['page' => $page->slug])
                : $this->resolveUrl($item);

            if ($url === null) {
                continue;
            }

            $label = $this->label($item);

            if ($label === '') {
                continue;
            }

            $image = $item['image'] ?? null;

            $resolved[] = [
                'label' => $label,
                'url' => $url,
                'imageUrl' => is_string($image) && trim($image) !== ''
                    ? asset('storage/'.$image)
                    : null,
                'active' => $page !== null
                    ? request()->route('page') instanceof Page && request()->route('page')->is($page)
                    : $this->isActive($item),
            ];
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveUrl(array $item): ?string
    {
        $target = is_string($item['target'] ?? null) ? trim($item['target']) : '';

        if ($target === '') {
            return null;
        }

        return match ($item['type'] ?? self::TYPE_ROUTE) {
            self::TYPE_ROUTE => in_array($target, self::ROUTES, true) && Route::has($target)
                ? route($target)
                : null,
            self::TYPE_PAGE => Route::has('page.show') ? route('page.show', ['page' => $target]) : null,
            self::TYPE_URL => $this->externalUrl($target),
            default => null,
        };
    }

    private function externalUrl(string $target): ?string
    {
        $scheme = parse_url($target, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return filter_var($target, FILTER_VALIDATE_URL) !== false ? $target : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function label(array $item): string
    {
        $ru = is_string($item['label_ru'] ?? null) ? trim($item['label_ru']) : '';
        $en = is_string($item['label_en'] ?? null) ? trim($item['label_en']) : '';

        if (app()->getLocale() === Locale::En->value) {
            return $en !== '' ? $en : $ru;
        }

        return $ru;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isActive(array $item): bool
    {
        if (($item['type'] ?? self::TYPE_ROUTE) !== self::TYPE_ROUTE) {
            return false;
        }

        $target = $item['target'] ?? null;

        return is_string($target) && request()->routeIs($target, str_replace('.index', '.*', $target));
    }

    /**
     * @param  array<int, array<string, mixed>>  $default
     * @return array<int, array<string, mixed>>
     */
    private function storedItems(string $key, array $default): array
    {
        $value = $this->settings->get($key);

        if (! is_array($value)) {
            return $default;
        }

        $items = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = array_merge([
                    'id' => '',
                    'label_ru' => '',
                    'label_en' => '',
                    'type' => self::TYPE_ROUTE,
                    'target' => '',
                    'visible' => true,
                    'image' => null,
                ], $item);
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $id,
        string $labelRu,
        string $labelEn,
        string $type,
        string $target,
    ): array {
        return [
            'id' => $id,
            'label_ru' => $labelRu,
            'label_en' => $labelEn,
            'type' => $type,
            'target' => $target,
            'visible' => true,
            'image' => null,
        ];
    }
}
