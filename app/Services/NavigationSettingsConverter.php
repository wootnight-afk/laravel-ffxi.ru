<?php

namespace App\Services;

use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class NavigationSettingsConverter
{
    public function __construct(
        private readonly SiteNavigation $navigation,
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(bool $lock = false): array
    {
        $settings = DB::table('settings')->orderBy('key');
        $pages = DB::table('pages')->orderBy('id');
        if ($lock) {
            $settings->lockForUpdate();
            $pages->lockForUpdate();
        }
        $settingsRows = $settings->get()->toArray();
        $pageRows = $pages->get()->toArray();

        return [
            'settings' => $settingsRows,
            'pages' => $pageRows,
            'settings_sha256' => hash('sha256', json_encode($settingsRows, JSON_THROW_ON_ERROR)),
            'pages_sha256' => hash('sha256', json_encode($pageRows, JSON_THROW_ON_ERROR)),
        ];
    }

    /** @return array<string, mixed> */
    public function preview(): array
    {
        return $this->plan($this->snapshot());
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function plan(array $snapshot): array
    {
        $values = [];
        foreach ($snapshot['settings'] as $row) {
            if (in_array($row->key, [SiteNavigation::KEY_HEADER_ITEMS, SiteNavigation::KEY_FOOTER_ITEMS], true)) {
                $values[$row->key] = $row->value === null
                    ? null
                    : json_decode($row->value, true, flags: JSON_THROW_ON_ERROR);
            }
        }
        $pages = [];
        foreach ($snapshot['pages'] as $row) {
            $pages[(string) $row->id] = (array) $row;
        }
        $conflicts = [];
        $menus = [];
        $flags = [];
        foreach ([SiteNavigation::KEY_HEADER_ITEMS, SiteNavigation::KEY_FOOTER_ITEMS] as $key) {
            $items = $values[$key] ?? ($key === SiteNavigation::KEY_HEADER_ITEMS
                ? $this->navigation->defaultHeaderItems()
                : $this->navigation->defaultFooterItems());
            if (! is_array($items) || ! array_is_list($items)) {
                $conflicts[] = $key.': not a JSON list';

                continue;
            }
            $seenPages = [];
            $seenIds = [];
            foreach ($items as $index => &$item) {
                if (! is_array($item)) {
                    $conflicts[] = $key.': invalid item '.$index;

                    continue;
                }
                $id = (string) ($item['id'] ?? '');
                if ($id === '' || isset($seenIds[$id])) {
                    $conflicts[] = $key.': missing or duplicate item ID '.$id;
                }
                $seenIds[$id] = true;
                $item['order'] ??= $index;
                if (($item['type'] ?? null) !== SiteNavigation::TYPE_PAGE) {
                    continue;
                }
                $target = (string) ($item['target'] ?? '');
                $matches = array_filter($pages, fn (array $page): bool => $page['deleted_at'] === null
                    && ($target === (string) $page['id'] || $target === $page['slug']));
                if (count($matches) !== 1) {
                    $conflicts[] = $key.': missing or ambiguous CMS target '.$target;

                    continue;
                }
                $page = reset($matches);
                $pageId = (string) $page['id'];
                if (isset($seenPages[$pageId]) || in_array($page['slug'], PageController::RESERVED_SLUGS, true)) {
                    $conflicts[] = $key.': duplicate or reserved CMS target '.$target;

                    continue;
                }
                $seenPages[$pageId] = true;
                $flag = $key === SiteNavigation::KEY_HEADER_ITEMS ? 'show_in_menu' : 'show_in_footer';
                $visible = (bool) ($item['visible'] ?? true);
                if ($flag === 'show_in_menu' && array_key_exists('visible', $item)
                    && $visible !== (bool) $page['show_in_menu']) {
                    $conflicts[] = $key.': CMS visible conflicts with show_in_menu for '.$target;
                }
                $flags[$pageId][$flag] = $flag === 'show_in_menu' || ! array_key_exists('visible', $item)
                    ? (bool) ($page[$flag] ?? false)
                    : $visible;
                $item['target'] = $pageId;
                unset($item['visible']);
            }
            unset($item);
            if ($conflicts !== []) {
                continue;
            }

            if ($key === SiteNavigation::KEY_HEADER_ITEMS) {
                $automatic = array_filter($pages, fn (array $page): bool => $page['deleted_at'] === null
                    && (bool) $page['is_published'] && (bool) $page['show_in_menu']
                    && ! isset($seenPages[(string) $page['id']])
                    && ! in_array($page['slug'], PageController::RESERVED_SLUGS, true));
                usort($automatic, fn (array $a, array $b): int => [$a['menu_order'], $a['title']] <=> [$b['menu_order'], $b['title']]);
                $order = $items === [] ? 0 : max(array_column($items, 'order')) + 1;
                foreach ($automatic as $page) {
                    $id = 'page-'.$page['id'];
                    if (isset($seenIds[$id])) {
                        $conflicts[] = $key.': generated item ID conflicts with '.$id;
                    }
                    $items[] = [
                        'id' => $id,
                        'type' => SiteNavigation::TYPE_PAGE,
                        'target' => (string) $page['id'],
                        'label_ru' => $page['title'],
                        'label_en' => $page['title_en'] ?? '',
                        'order' => $order++,
                        'image' => null,
                    ];
                }
                $homeItems = array_filter($items, fn (array $item): bool => ($item['type'] ?? null) === SiteNavigation::TYPE_ROUTE
                    && ($item['target'] ?? null) === 'home');
                if (count($homeItems) > 1) {
                    $conflicts[] = $key.': multiple Home logo bindings';
                }
                if ($homeItems === []) {
                    if (isset($seenIds['home-logo'])) {
                        $conflicts[] = $key.': Home logo ID conflict';
                    }
                    array_unshift($items, [
                        'id' => 'home-logo', 'type' => SiteNavigation::TYPE_ROUTE, 'target' => 'home',
                        'label_ru' => 'Главная', 'label_en' => 'Home', 'visible' => true, 'order' => -1, 'image' => null,
                    ]);
                }
            }
            $menus[$key] = $items;
        }

        return [
            'snapshot' => $snapshot,
            'menus' => $menus,
            'page_flags' => $flags,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array{changed: bool, snapshot_path: ?string}
     */
    public function apply(array $preview): array
    {
        try {
            return DB::transaction(function () use ($preview): array {
                $snapshot = $this->snapshot(lock: true);
                foreach (['settings_sha256', 'pages_sha256'] as $hash) {
                    if ($snapshot[$hash] !== $preview['snapshot'][$hash]) {
                        throw new RuntimeException('Navigation source snapshot changed: '.$hash);
                    }
                }
                $plan = $this->plan($snapshot);
                if ($plan['conflicts'] !== []) {
                    throw new RuntimeException(implode('; ', $plan['conflicts']));
                }
                $changes = [];
                foreach ($plan['menus'] as $key => $items) {
                    if (Setting::find($key)?->value != $items) {
                        $changes[$key] = $items;
                    }
                }
                $pageChanges = [];
                foreach ($plan['page_flags'] as $id => $flags) {
                    $page = Page::findOrFail($id);
                    $page->fill($flags);
                    if ($page->isDirty()) {
                        $pageChanges[] = $page;
                    }
                }
                if ($changes === [] && $pageChanges === []) {
                    return ['changed' => false, 'snapshot_path' => null];
                }
                $path = 'navigation-conversions/'.now()->format('Ymd-His').'-'.$snapshot['settings_sha256'].'.json';
                $serialized = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if (! Storage::disk('local')->put($path, $serialized)
                    || Storage::disk('local')->get($path) !== $serialized) {
                    throw new RuntimeException('Navigation snapshot integrity check failed.');
                }
                foreach ($changes as $key => $items) {
                    Setting::updateOrCreate(['key' => $key], ['value' => $items]);
                }
                foreach ($pageChanges as $page) {
                    $page->saveOrFail();
                }
                $this->audit->log('navigation.converted', old: [
                    'settings_sha256' => $snapshot['settings_sha256'],
                    'pages_sha256' => $snapshot['pages_sha256'],
                    'snapshot_path' => $path,
                ], new: ['menus' => $changes, 'page_flags' => $plan['page_flags']], recordIp: false);

                return ['changed' => true, 'snapshot_path' => $path];
            });
        } finally {
            $this->settings->flush();
        }
    }
}
