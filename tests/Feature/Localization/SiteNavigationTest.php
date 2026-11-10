<?php

declare(strict_types=1);

use App\Filament\Pages\FooterPage;
use App\Filament\Pages\HeaderPage;
use App\Models\Page;
use App\Models\User;
use App\Services\HomeImageUploader;
use App\Services\SettingsRepository;
use App\Services\SiteNavigation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function navUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

/**
 * @param  array<int, array<string, mixed>>  $items
 */
function storeNavigationItems(string $key, array $items): void
{
    app(SettingsRepository::class)->setMany([$key => $items]);
}

it('renders the default header and footer navigation', function () {
    $this->withSession(['locale' => 'ru'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Новости игры')
        ->assertSee('Галерея')
        ->assertSee('Игроки')
        ->assertSee('Контакты')
        ->assertSee(SiteNavigation::DEFAULT_FOOTER_TEXT);
});

it('renders the configured items in the requested order and skips hidden ones', function () {
    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, [
        [
            'id' => 'custom-a',
            'label_ru' => 'Правила',
            'label_en' => 'Rules',
            'type' => SiteNavigation::TYPE_URL,
            'target' => 'https://example.com/rules',
            'visible' => true,
            'image' => null,
        ],
        [
            'id' => 'custom-hidden',
            'label_ru' => 'Скрытый пункт',
            'label_en' => 'Hidden item',
            'type' => SiteNavigation::TYPE_ROUTE,
            'target' => 'contacts',
            'visible' => false,
            'image' => null,
        ],
    ]);

    $this->withSession(['locale' => 'ru'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('https://example.com/rules')
        ->assertSee('Правила')
        ->assertDontSee('Скрытый пункт')
        // Replaced navigation: the defaults are gone.
        ->assertDontSee('Новости игры');
});

it('falls back to the russian label when the english one is empty', function () {
    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, [
        [
            'id' => 'fallback',
            'label_ru' => 'Правила',
            'label_en' => '',
            'type' => SiteNavigation::TYPE_URL,
            'target' => 'https://example.com/rules',
            'visible' => true,
            'image' => null,
        ],
    ]);

    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Правила')
        ->assertDontSee('Rules');
});

it('drops items with unsupported targets', function () {
    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, [
        [
            'id' => 'script',
            'label_ru' => 'Опасная ссылка',
            'label_en' => 'Unsafe link',
            'type' => SiteNavigation::TYPE_URL,
            'target' => 'javascript:alert(1)',
            'visible' => true,
            'image' => null,
        ],
        [
            'id' => 'unknown-route',
            'label_ru' => 'Неизвестный маршрут',
            'label_en' => 'Unknown route',
            'type' => SiteNavigation::TYPE_ROUTE,
            'target' => 'admin.dashboard',
            'visible' => true,
            'image' => null,
        ],
    ]);

    $this->withSession(['locale' => 'ru'])
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('Опасная ссылка')
        ->assertDontSee('Неизвестный маршрут')
        ->assertDontSee('javascript:alert(1)');
});

it('links header items to published cms pages', function () {
    $page = Page::create([
        'title' => 'Правила сервера',
        'slug' => 'rules',
        'body' => 'Правила сервера.',
        'is_published' => true,
        'show_in_menu' => true,
    ]);

    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, [
        [
            'id' => 'page-link',
            'label_ru' => 'Правила сервера',
            'label_en' => 'Server rules',
            'type' => SiteNavigation::TYPE_PAGE,
            'target' => (string) $page->getKey(),
            'visible' => true,
            'image' => null,
        ],
    ]);

    $this->withSession(['locale' => 'ru'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('page.show', ['page' => $page->slug]));
});

it('requires explicit bindings for pages marked show in menu', function () {
    $page = Page::create([
        'title' => 'Правила',
        'title_en' => 'Rules',
        'slug' => 'rules',
        'body' => 'Правила.',
        'is_published' => true,
        'show_in_menu' => true,
        'menu_order' => 1,
    ]);
    $this->get(route('home'))->assertDontSee(route('page.show', ['page' => 'rules']));
    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, [[
        'id' => 'page-rules', 'type' => 'page', 'target' => (string) $page->getKey(),
        'label_ru' => 'Правила', 'label_en' => 'Rules',
    ]]);
    Page::create([
        'title' => 'Черновик',
        'slug' => 'draft',
        'body' => 'Черновик.',
        'is_published' => false,
        'show_in_menu' => true,
    ]);

    $this->withSession(['locale' => 'ru'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Правила')
        ->assertSee(route('page.show', ['page' => 'rules']))
        ->assertDontSee('Черновик');

    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Rules');
});

it('does not duplicate a page linked explicitly in the header', function () {
    $page = Page::create([
        'title' => 'Правила',
        'slug' => 'rules',
        'body' => 'Правила.',
        'is_published' => true,
        'show_in_menu' => true,
    ]);

    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, [
        [
            'id' => 'page-link',
            'label_ru' => 'Правила сервера',
            'label_en' => 'Server rules',
            'type' => SiteNavigation::TYPE_PAGE,
            'target' => (string) $page->getKey(),
            'visible' => true,
            'image' => null,
        ],
    ]);

    $html = $this->withSession(['locale' => 'ru'])->get(route('home'))->getContent();

    expect(substr_count($html, route('page.show', ['page' => 'rules'])))->toBe(1);
});

it('resolves cms bindings by id after slug changes and requires the matching menu flag', function () {
    $page = Page::create([
        'title' => 'Stable link', 'slug' => 'before', 'body' => 'Body',
        'is_published' => true, 'show_in_menu' => true, 'show_in_footer' => false,
    ]);
    $items = [[
        'id' => 'stable', 'type' => 'page', 'target' => (string) $page->getKey(),
        'label_ru' => 'Stable link', 'label_en' => 'Stable link',
    ]];
    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, $items);
    storeNavigationItems(SiteNavigation::KEY_FOOTER_ITEMS, $items);
    $page->update(['slug' => 'after']);
    $navigation = app(SiteNavigation::class);
    expect($navigation->headerItems()[0]['url'])->toBe(route('page.show', ['page' => 'after']))
        ->and($navigation->footerItems())->toBe([]);
    $page->update(['show_in_menu' => false, 'show_in_footer' => true]);
    expect($navigation->headerItems())->toBe([])
        ->and($navigation->footerItems())->toHaveCount(1);
    $page->update(['is_published' => false]);
    expect($navigation->footerItems())->toBe([]);
});

it('hides the home link without hiding its logo or adding a text menu item', function () {
    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, [[
        'id' => 'home-logo', 'type' => 'route', 'target' => 'home',
        'label_ru' => 'Home label', 'label_en' => 'Home label', 'visible' => false,
    ]]);
    $this->get(route('home'))->assertOk()
        ->assertSee('<span class="logo-link">', false)
        ->assertSee('img/bx_default_logo.webp')
        ->assertDontSee('Home label');
});

it('synchronizes cms visibility through the header editor without deleting its page', function () {
    $page = Page::create([
        'title' => 'Keep content', 'slug' => 'keep-content', 'body' => 'Keep body',
        'is_published' => true, 'show_in_menu' => true,
    ]);
    Livewire::actingAs(navUser('editor'))->test(HeaderPage::class)
        ->set('data.items', [[
            'id' => 'keep', 'type' => 'page', 'page' => (string) $page->getKey(),
            'label_ru' => 'Keep label', 'label_en' => 'Keep label', 'visible' => false,
        ]])->call('save')->assertHasNoErrors();
    expect($page->refresh()->show_in_menu)->toBeFalse()
        ->and($page->trashed())->toBeFalse()
        ->and($page->body)->toBe('Keep body');
    $binding = app(SettingsRepository::class)->get(SiteNavigation::KEY_HEADER_ITEMS)[0];
    expect($binding)->not->toHaveKey('visible')
        ->and($binding['target'])->toBe((string) $page->getKey());
});

it('marks the active header item', function () {
    storeNavigationItems(SiteNavigation::KEY_HEADER_ITEMS, [
        [
            'id' => 'news',
            'label_ru' => 'Новости игры',
            'label_en' => 'Game news',
            'type' => SiteNavigation::TYPE_ROUTE,
            'target' => 'news.index',
            'visible' => true,
            'image' => null,
        ],
    ]);

    $this->withSession(['locale' => 'ru'])
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('class="active"', false);
});

it('uses the localized footer text with a per-field fallback', function () {
    app(SettingsRepository::class)->setMany([
        SiteNavigation::KEY_FOOTER_TEXT_RU => 'Русский текст футера',
        SiteNavigation::KEY_FOOTER_TEXT_EN => '',
    ]);

    $this->withSession(['locale' => 'ru'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Русский текст футера');

    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Русский текст футера')
        ->assertDontSee('English footer text');

    app(SettingsRepository::class)->setMany([
        SiteNavigation::KEY_FOOTER_TEXT_EN => 'English footer text',
    ]);

    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('English footer text');
});

it('stores navigation icons in the dedicated uploader slot', function () {
    Storage::fake('public');

    $stored = app(HomeImageUploader::class)->upload(
        UploadedFile::fake()->image('icon.png', 32, 32),
        HomeImageUploader::SLOT_NAV,
    );

    expect($stored['path'])->toStartWith('nav/')
        ->and($stored['width'])->toBe(32)
        ->and($stored['height'])->toBe(32);

    Storage::disk('public')->assertExists($stored['path']);
});

it('allows editors to manage the header navigation', function () {
    $editor = navUser('editor');

    $this->actingAs($editor)
        ->get('/admin/header')
        ->assertOk()
        ->assertSee('Header-меню');
});

it('denies the header and footer editors to regular users', function () {
    $user = navUser('user');

    $this->actingAs($user)->get('/admin/header')->assertForbidden();
    $this->actingAs($user)->get('/admin/footer')->assertForbidden();
});

it('saves header items through the admin page', function () {
    $admin = navUser('admin');

    Livewire::actingAs($admin)
        ->test(HeaderPage::class)
        ->set('data.items', [
            [
                'id' => 'custom',
                'label_ru' => 'Правила',
                'label_en' => 'Rules',
                'type' => SiteNavigation::TYPE_URL,
                'url' => 'https://example.com/rules',
                'visible' => true,
                'image' => null,
            ],
            [
                'id' => '',
                'label_ru' => '',
                'label_en' => '',
                'type' => SiteNavigation::TYPE_ROUTE,
                'route' => 'home',
                'visible' => true,
                'image' => null,
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $items = app(SettingsRepository::class)->get(SiteNavigation::KEY_HEADER_ITEMS);

    expect($items)->toBeArray()->toHaveCount(1);

    $item = $items[0];

    expect($item['id'])->toBe('custom')
        ->and($item['label_ru'])->toBe('Правила')
        ->and($item['label_en'])->toBe('Rules')
        ->and($item['type'])->toBe(SiteNavigation::TYPE_URL)
        ->and($item['target'])->toBe('https://example.com/rules')
        ->and($item['visible'])->toBeTrue();
});

it('saves footer text and links through the admin page', function () {
    $admin = navUser('admin');

    Livewire::actingAs($admin)
        ->test(FooterPage::class)
        ->set('data.text', [
            'text_ru' => 'Текст футера',
            'text_en' => 'Footer text',
        ])
        ->set('data.items', [
            [
                'id' => 'contacts',
                'label_ru' => 'Контакты',
                'label_en' => 'Contacts',
                'type' => SiteNavigation::TYPE_ROUTE,
                'route' => 'contacts',
                'visible' => true,
                'image' => null,
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsRepository::class);

    expect($settings->get(SiteNavigation::KEY_FOOTER_TEXT_RU))->toBe('Текст футера')
        ->and($settings->get(SiteNavigation::KEY_FOOTER_TEXT_EN))->toBe('Footer text');

    $items = $settings->get(SiteNavigation::KEY_FOOTER_ITEMS);

    expect($items)->toBeArray()->toHaveCount(1)
        ->and($items[0]['target'])->toBe('contacts');
});

it('drops an item without a target instead of breaking the layout', function () {
    $admin = navUser('admin');

    Livewire::actingAs($admin)
        ->test(HeaderPage::class)
        ->set('data.items', [
            [
                'id' => '',
                'label_ru' => 'Без цели',
                'label_en' => 'Without target',
                'type' => SiteNavigation::TYPE_URL,
                'url' => '',
                'visible' => true,
                'image' => null,
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get(SiteNavigation::KEY_HEADER_ITEMS))->toBe([]);
});
