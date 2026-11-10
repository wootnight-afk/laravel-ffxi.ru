<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PageMenuManager
{
    /** @var array<string, string> */
    private const MENUS = [
        SiteNavigation::KEY_HEADER_ITEMS => 'show_in_menu',
        SiteNavigation::KEY_FOOTER_ITEMS => 'show_in_footer',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SiteNavigation $navigation,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Page $page, array $data): Page
    {
        try {
            return DB::transaction(function () use ($page, $data): Page {
                $menus = $this->lockedMenus();
                if ($page->exists) {
                    $page = Page::query()->lockForUpdate()->findOrFail($page->getKey());
                }

                $previousSlug = $page->slug;
                $previous = $page->getAttributes();
                $isNew = ! $page->exists;

                $page->fill($data);
                if (! $page->save()) {
                    throw new RuntimeException('CMS page saving was cancelled.');
                }

                if ($isNew) {
                    $this->audit->log('page.created', $page, null, $page->getAttributes(), recordIp: false);
                } elseif ($page->wasChanged()) {
                    $this->audit->log('page.updated', $page, $previous, $page->getChanges(), recordIp: false);
                }

                $updated = $menus;
                foreach (self::MENUS as $key => $flag) {
                    // Selecting a flag is an explicit menu choice. Switching it
                    // off retains the binding and its editorial metadata.
                    if (! $page->getAttribute($flag)) {
                        continue;
                    }

                    $items = [];
                    $bound = false;

                    foreach ($updated[$key] as $item) {
                        if (! $this->belongsTo($item, $page)
                            && ! (($item['type'] ?? null) === SiteNavigation::TYPE_PAGE && ($item['target'] ?? null) === $previousSlug)) {
                            $items[] = $item;

                            continue;
                        }

                        // Duplicate bindings of the same page are collapsed
                        // into the first one.
                        if ($bound) {
                            continue;
                        }

                        $bound = true;

                        // Labels follow the page, the target is the canonical
                        // page key. `order` stays with the Header/Footer editor:
                        // it is the only source of truth of the menu order, so
                        // an existing binding is never renumbered here.
                        $item['target'] = (string) $page->getKey();
                        $item['label_ru'] = (string) $page->title;
                        $item['label_en'] = (string) ($page->title_en ?? '');
                        $item['order'] ??= (int) $page->menu_order;
                        unset($item['visible']);

                        $items[] = $item;
                    }

                    if (! $bound) {
                        $items[] = [
                            'id' => 'page-'.$page->getKey(),
                            'type' => SiteNavigation::TYPE_PAGE,
                            'target' => (string) $page->getKey(),
                            'label_ru' => $page->title,
                            'label_en' => $page->title_en ?? '',
                            'order' => (int) $page->menu_order,
                            'image' => null,
                        ];
                    }

                    $updated[$key] = $items;
                }

                $this->persistMenus($page, $menus, $updated);

                return $page;
            });
        } finally {
            $this->settings->flush();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $extra
     */
    public function saveMenu(string $key, array $items, array $extra = []): void
    {
        if (! isset(self::MENUS[$key])) {
            throw new RuntimeException('Unknown navigation menu.');
        }
        try {
            DB::transaction(function () use ($key, $items, $extra): void {
                $old = $this->lockedMenus();
                $seen = [];
                foreach ($items as &$item) {
                    if (($item['type'] ?? null) !== SiteNavigation::TYPE_PAGE) {
                        continue;
                    }
                    $id = (string) ($item['target'] ?? '');
                    if (! ctype_digit($id) || isset($seen[$id])) {
                        throw new RuntimeException('Invalid or duplicate CMS navigation target.');
                    }
                    $seen[$id] = true;
                    $page = Page::query()->lockForUpdate()->findOrFail((int) $id);
                    $previous = $page->getAttributes();
                    $page->update([self::MENUS[$key] => (bool) ($item['visible'] ?? $page->getAttribute(self::MENUS[$key]))]);
                    unset($item['visible']);
                    if ($page->wasChanged()) {
                        $this->audit->log('page.updated', $page, $previous, $page->getChanges(), recordIp: false);
                    }
                }
                unset($item);
                $values = [$key => $items] + $extra;
                foreach ($values as $settingKey => $value) {
                    Setting::updateOrCreate(['key' => $settingKey], ['value' => $value]);
                }
                $this->audit->log($key === SiteNavigation::KEY_HEADER_ITEMS ? 'header.updated' : 'footer.updated',
                    old: [$key => $old[$key]], new: $values, recordIp: false);
            });
        } finally {
            $this->settings->flush();
        }
    }

    /**
     * Show or hide a system route in the header and footer.
     *
     * System pages have no model, so their menu entry is a `route` item. An
     * existing item is only toggled: its label, order and icon are preserved.
     * A missing item is appended when the route is switched on.
     */
    public function setRouteVisibility(
        string $route,
        bool $showInMenu,
        bool $showInFooter,
        string $labelRu,
        string $labelEn,
    ): void {
        try {
            DB::transaction(function () use ($route, $showInMenu, $showInFooter, $labelRu, $labelEn): void {
                $menus = $this->lockedMenus();
                $updated = $menus;

                // The home entry is the logo link of the header, not a regular
                // menu item: the layout renders it outside the item list, so
                // the toggle must still be persisted to hide or show the link.
                $isHome = $route === 'home';

                foreach ([
                    SiteNavigation::KEY_HEADER_ITEMS => $showInMenu,
                    SiteNavigation::KEY_FOOTER_ITEMS => $showInFooter,
                ] as $key => $visible) {
                    $found = false;

                    foreach ($updated[$key] as &$item) {
                        if (($item['type'] ?? null) !== SiteNavigation::TYPE_ROUTE
                            || ($item['target'] ?? null) !== $route) {
                            continue;
                        }

                        $item['visible'] = $visible;
                        $item['label_ru'] = $labelRu;
                        $item['label_en'] = $labelEn;
                        $found = true;
                    }
                    unset($item);

                    $createHiddenHome = $isHome && $key === SiteNavigation::KEY_HEADER_ITEMS;

                    if (! $found && ($visible || $createHiddenHome)) {
                        $updated[$key][] = [
                            'id' => 'route-'.$route,
                            'label_ru' => $labelRu,
                            'label_en' => $labelEn,
                            'type' => SiteNavigation::TYPE_ROUTE,
                            'target' => $route,
                            'visible' => $visible,
                            'order' => count($updated[$key]),
                            'image' => null,
                        ];
                    }
                }

                foreach ($updated as $key => $items) {
                    if ($items === $menus[$key]) {
                        continue;
                    }

                    Setting::updateOrCreate(['key' => $key], ['value' => $items]);
                    $this->audit->log(
                        action: 'navigation.visibility_updated',
                        old: [$key => $menus[$key]],
                        new: [$key => $items],
                        recordIp: false,
                    );
                }
            });
        } finally {
            $this->settings->flush();
        }
    }

    public function delete(Page $page): bool
    {
        try {
            return DB::transaction(function () use ($page): bool {
                $menus = $this->lockedMenus();
                $page = Page::query()->lockForUpdate()->findOrFail($page->getKey());
                $old = $page->getAttributes();
                $updated = [];
                foreach ($menus as $key => $items) {
                    $updated[$key] = array_values(array_filter(
                        $items,
                        fn (array $item): bool => ! $this->belongsTo($item, $page),
                    ));
                }

                if (! $page->delete()) {
                    throw new RuntimeException('CMS page deletion was cancelled.');
                }

                $this->persistMenus($page, $menus, $updated);
                $this->audit->log('page.deleted', $page, $old, $page->getAttributes(), recordIp: false);

                return true;
            });
        } finally {
            $this->settings->flush();
        }
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function lockedMenus(): array
    {
        $menus = [];
        foreach (array_keys(self::MENUS) as $key) {
            $setting = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $items = $setting !== null
                ? json_decode($setting->value, true, flags: JSON_THROW_ON_ERROR)
                : match ($key) {
                    SiteNavigation::KEY_HEADER_ITEMS => $this->navigation->defaultHeaderItems(),
                    default => $this->navigation->defaultFooterItems(),
                };
            if (! is_array($items) || ! array_is_list($items)) {
                throw new RuntimeException('Malformed navigation setting: '.$key);
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new RuntimeException('Malformed navigation item: '.$key);
                }
            }
            $menus[$key] = $items;
        }

        return $menus;
    }

    /** @param array<string, mixed> $item */
    private function belongsTo(array $item, Page $page): bool
    {
        return ($item['type'] ?? null) === SiteNavigation::TYPE_PAGE
            && in_array((string) ($item['target'] ?? ''), [(string) $page->getKey(), $page->slug], true);
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $old
     * @param  array<string, array<int, array<string, mixed>>>  $new
     */
    private function persistMenus(Page $page, array $old, array $new): void
    {
        foreach ($new as $key => $items) {
            if ($items === $old[$key]) {
                continue;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $items]);
            $this->audit->log('page.navigation_updated', $page, [$key => $old[$key]], [$key => $items], recordIp: false);
        }
    }
}
