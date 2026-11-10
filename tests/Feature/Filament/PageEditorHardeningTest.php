<?php

declare(strict_types=1);

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\AdminAuditLog;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContactsPageContent;
use App\Services\EventsCreatePageContent;
use App\Services\EventsPageContent;
use App\Services\GalleryPageContent;
use App\Services\HomeImageUploader;
use App\Services\HomePageContent;
use App\Services\NewsPageContent;
use App\Services\PageMenuManager;
use App\Services\SettingsRepository;
use App\Services\SiteNavigation;
use App\Services\SitePageRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function hardeningUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function hardeningPage(array $overrides = []): Page
{
    return Page::create(array_merge([
        'title' => 'Hardening '.uniqid(),
        'slug' => 'hardening-'.uniqid(),
        'body' => 'Body',
        'is_published' => false,
        'show_in_menu' => false,
        'menu_order' => 0,
    ], $overrides));
}

// ------------------------------------------------------------------
// System page SEO (audit §2.1 / §3.1)
// ------------------------------------------------------------------

it('saves and renders the localized meta description of every system page', function () {
    $admin = hardeningUser('admin');
    $settings = app(SettingsRepository::class);

    $pages = [
        'sys.home' => [HomePageContent::class, '/'],
        'sys.news.index' => [NewsPageContent::class, '/news'],
        'sys.gallery.index' => [GalleryPageContent::class, '/gallery'],
        'sys.contacts' => [ContactsPageContent::class, '/contacts'],
        'sys.events.index' => [EventsPageContent::class, '/events'],
    ];

    foreach ($pages as $record => [$provider, $url]) {
        Livewire::actingAs($admin)->test(EditPage::class, ['record' => $record])
            ->set('data.meta_description', 'RU '.$record)
            ->set('data.meta_description_en', 'EN '.$record)
            ->call('save')
            ->assertHasNoErrors();

        expect($settings->get($provider::KEY_META_DESCRIPTION))->toBe('RU '.$record)
            ->and($settings->get($provider::KEY_META_DESCRIPTION_EN))->toBe('EN '.$record);

        $this->withSession(['locale' => 'ru'])->get($url)
            ->assertOk()
            ->assertSee('<meta name="description" content="RU '.$record.'">', false);

        $this->withSession(['locale' => 'en'])->get($url)
            ->assertOk()
            ->assertSee('<meta name="description" content="EN '.$record.'">', false);
    }
});

it('renders the meta description of the event creation page', function () {
    $admin = hardeningUser('admin');

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.events.create'])
        ->set('data.meta_description', 'Create an event')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get(EventsCreatePageContent::KEY_META_DESCRIPTION))
        ->toBe('Create an event');

    $this->actingAs($admin)->get('/events/create')
        ->assertOk()
        ->assertSee('<meta name="description" content="Create an event">', false);
});

it('falls back to the russian meta description when the english one is empty', function () {
    app(SettingsRepository::class)->setMany([
        NewsPageContent::KEY_META_DESCRIPTION => 'Русское описание новостей.',
        NewsPageContent::KEY_META_DESCRIPTION_EN => '',
    ]);

    $this->withSession(['locale' => 'en'])->get('/news')
        ->assertOk()
        ->assertSee('<meta name="description" content="Русское описание новостей.">', false);
});

// ------------------------------------------------------------------
// Menu synchronization (audit §2.2 / §3.2, §2.7 / §4.1)
// ------------------------------------------------------------------

it('synchronizes binding labels and collapses duplicates without renumbering the menu', function () {
    $page = hardeningPage(['title' => 'Old title', 'title_en' => 'Old EN', 'show_in_menu' => true, 'menu_order' => 5]);
    $settings = app(SettingsRepository::class);

    $settings->set(SiteNavigation::KEY_HEADER_ITEMS, [
        ['id' => 'first', 'type' => 'page', 'target' => (string) $page->getKey(), 'label_ru' => 'Old title', 'label_en' => 'Old EN', 'order' => 40, 'visible' => false],
        ['id' => 'duplicate', 'type' => 'page', 'target' => (string) $page->getKey(), 'label_ru' => 'Duplicate', 'label_en' => '', 'order' => 41],
    ]);

    app(PageMenuManager::class)->save($page, [
        'title' => 'New title',
        'title_en' => 'New EN',
        'show_in_menu' => true,
        'show_in_footer' => false,
        'menu_order' => 5,
    ]);

    $header = $settings->get(SiteNavigation::KEY_HEADER_ITEMS);

    expect($header)->toHaveCount(1)
        ->and($header[0]['label_ru'])->toBe('New title')
        ->and($header[0]['label_en'])->toBe('New EN')
        // The Header/Footer editor owns the order of an existing binding.
        ->and($header[0]['order'])->toBe(40)
        ->and($header[0])->not->toHaveKey('visible');
});

it('uses menu_order as the initial order of a new binding', function () {
    $page = hardeningPage(['menu_order' => 12]);

    app(PageMenuManager::class)->save($page, ['show_in_menu' => true]);

    $header = app(SettingsRepository::class)->get(SiteNavigation::KEY_HEADER_ITEMS);

    expect(end($header)['order'])->toBe(12);
});

it('stores independent ru and en labels for system menu entries', function () {
    $admin = hardeningUser('admin');
    $settings = app(SettingsRepository::class);

    // Previous behaviour copied the same localized title into both labels.
    $settings->set(SiteNavigation::KEY_HEADER_ITEMS, [
        ['id' => 'news', 'type' => 'route', 'target' => 'news.index', 'label_ru' => 'Новости', 'label_en' => 'Новости', 'order' => 0],
    ]);

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.news.index'])
        ->set('data.show_in_menu', true)
        ->call('save')
        ->assertHasNoErrors();

    $item = collect($settings->get(SiteNavigation::KEY_HEADER_ITEMS))->firstWhere('target', 'news.index');

    expect($item['label_ru'])->toBe(__('site.nav.news', [], 'ru'))
        ->and($item['label_en'])->toBe(__('site.nav.news', [], 'en'));
});

// ------------------------------------------------------------------
// CMS creation and slug rules (audit §2.4 / §3.3, §2.5 / §3.4)
// ------------------------------------------------------------------

it('creates a published cms page with the requested menu order', function () {
    $editor = hardeningUser('editor');

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Published page')
        ->set('data.slug', 'published-page')
        ->set('data.is_published', true)
        ->set('data.menu_order', 9)
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::query()->where('slug', 'published-page')->firstOrFail();

    expect($page->is_published)->toBeTrue()
        ->and($page->menu_order)->toBe(9);
});

it('creates a draft cms page by default', function () {
    $editor = hardeningUser('editor');

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Draft page')
        ->set('data.slug', 'draft-page')
        ->call('save')
        ->assertHasNoErrors();

    expect(Page::query()->where('slug', 'draft-page')->firstOrFail()->is_published)->toBeFalse();
});

it('rejects invalid and reserved slugs', function (string $slug) {
    $editor = hardeningUser('editor');

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Slug check')
        ->set('data.slug', $slug)
        ->call('save')
        ->assertHasErrors(['data.slug']);
})->with([
    'uppercase' => ['Bad-Slug'],
    'underscore' => ['bad_slug'],
    'cyrillic' => ['плохой-slug'],
    'leading hyphen' => ['-bad'],
    'trailing hyphen' => ['bad-'],
    'reserved contacts' => ['contacts'],
    'reserved mfa' => ['mfa'],
    'reserved storage' => ['storage'],
]);

it('allows reusing the slug of a soft deleted page', function () {
    $editor = hardeningUser('editor');
    $old = hardeningPage(['slug' => 'reusable-slug']);
    $old->delete();

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Reused slug')
        ->set('data.slug', 'reusable-slug')
        ->call('save')
        ->assertHasNoErrors();

    expect(Page::query()->where('slug', 'reusable-slug')->count())->toBe(1);
});

// ------------------------------------------------------------------
// Atomic system save (audit §2.6 / §3.5)
// ------------------------------------------------------------------

it('keeps the previous image and removes the new file when the transaction fails', function () {
    Storage::fake('public');

    $admin = hardeningUser('admin');
    $settings = app(SettingsRepository::class);

    Storage::disk('public')->put('home/image/old_image.webp', 'old');
    $settings->setMany([
        HomePageContent::KEY_MAIN_IMAGE => 'home/image/old_image.webp',
        HomePageContent::KEY_MAIN_IMAGE_WIDTH => 640,
        HomePageContent::KEY_MAIN_IMAGE_HEIGHT => 480,
    ]);

    $event = 'eloquent.saving: '.Setting::class;
    Event::listen($event, function (Setting $setting): void {
        if ($setting->key === SiteNavigation::KEY_HEADER_ITEMS) {
            throw new RuntimeException('Injected navigation failure');
        }
    });

    try {
        Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.home'])
            ->set('data.main_image', UploadedFile::fake()->image('new.jpg', 300, 200))
            ->call('save');
    } finally {
        Event::forget($event);
    }

    expect($settings->get(HomePageContent::KEY_MAIN_IMAGE))->toBe('home/image/old_image.webp')
        ->and(Storage::disk('public')->exists('home/image/old_image.webp'))->toBeTrue()
        ->and(Storage::disk('public')->files('home/image'))->toBe(['home/image/old_image.webp']);
});

it('fails loudly when a provider constant is missing', function () {
    $admin = hardeningUser('admin');
    $component = Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.home'])->instance();

    $method = new ReflectionMethod($component, 'key');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($component, HomePageContent::class, 'KEY_DOES_NOT_EXIST'))
        ->toThrow(RuntimeException::class);
});

// ------------------------------------------------------------------
// CMS audit (audit §2.8 / §4.2)
// ------------------------------------------------------------------

it('audits cms creation, content updates, publication and unpublishing', function () {
    $editor = hardeningUser('editor');

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Audited page')
        ->set('data.slug', 'audited-page')
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::query()->where('slug', 'audited-page')->firstOrFail();

    expect(AdminAuditLog::query()->where('action', 'page.created')->where('subject_id', $page->getKey())->count())->toBe(1);

    Livewire::actingAs($editor)->test(EditPage::class, ['record' => 'cms-'.$page->getKey()])
        ->set('data.title', 'Audited page v2')
        ->call('save')
        ->assertHasNoErrors();

    expect(AdminAuditLog::query()->where('action', 'page.updated')->where('subject_id', $page->getKey())->count())->toBe(1);

    Livewire::actingAs($editor)->test(ListPages::class)
        ->callTableAction('publish', $page)
        ->assertHasNoTableActionErrors();

    Livewire::actingAs($editor)->test(ListPages::class)
        ->callTableAction('unpublish', $page)
        ->assertHasNoTableActionErrors();

    expect(AdminAuditLog::query()->where('action', 'page.published')->where('subject_id', $page->getKey())->count())->toBe(1)
        ->and(AdminAuditLog::query()->where('action', 'page.unpublished')->where('subject_id', $page->getKey())->count())->toBe(1);
});

// ------------------------------------------------------------------
// Route registry (audit §2.9 / §4.3, §2.10 / §4.4)
// ------------------------------------------------------------------

it('classifies authentication pages separately from public pages', function () {
    $routes = collect(app(SitePageRegistry::class)->classification());

    foreach (['login', 'register', 'password.request', 'verification.notice', 'mfa.challenge'] as $name) {
        expect($routes->firstWhere('name', $name)['category'])->toBe('auth');
    }

    expect($routes->firstWhere('name', 'home')['category'])->toBe('public')
        ->and($routes->firstWhere('name', 'page.show')['category'])->toBe('public');
});

it('reports missing classified routes without failing the registry', function () {
    $page = hardeningPage();
    $router = app('router');

    Route::shouldReceive('getRoutes')->andReturn(new RouteCollection);

    try {
        $registry = app(SitePageRegistry::class);

        expect(collect($registry->records())->filter(
            fn (array|Page $record): bool => $record instanceof Page && $record->is($page),
        ))->toHaveCount(1);

        $missing = collect($registry->classification())->where('category', 'missing');

        expect($missing)->not->toBeEmpty()
            ->and($missing->firstWhere('name', 'home'))->not->toBeNull();
    } finally {
        Route::swap($router);
    }
});

// ------------------------------------------------------------------
// Title / meta description output (audit §2.11 / §5.1)
// ------------------------------------------------------------------

it('renders special characters of the title and meta description as escaped data', function (string $record, string $url) {
    $admin = hardeningUser('admin');

    $description = '" onload="alert(1)<script>alert(2)</script>';
    $title = 'Guides & News <b>bold</b>';

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => $record])
        ->set('data.meta_description', $description)
        ->set('data.meta_title', $title)
        ->call('save')
        ->assertHasNoErrors();

    $this->actingAs($admin)->withSession(['locale' => 'ru'])->get($url)
        ->assertOk()
        // The attribute closes, so no markup from the value can execute.
        ->assertDontSee('<script>alert(2)</script>', false)
        ->assertSee('<meta name="description" content="&quot; onload=&quot;alert(1)&lt;script&gt;alert(2)&lt;/script&gt;">', false)
        ->assertSee('<title>Guides &amp; News &lt;b&gt;bold&lt;/b&gt;</title>', false)
        // Escaped once: `&` may not turn into `&amp;amp;`.
        ->assertDontSee('&amp;amp;', false);
})->with([
    'home' => ['sys.home', '/'],
    'news' => ['sys.news.index', '/news'],
    'gallery' => ['sys.gallery.index', '/gallery'],
    'contacts' => ['sys.contacts', '/contacts'],
    'events' => ['sys.events.index', '/events'],
    'events create' => ['sys.events.create', '/events/create'],
]);

it('escapes the title and meta description of a cms page', function () {
    $admin = hardeningUser('admin');

    Livewire::actingAs($admin)->test(CreatePage::class)
        ->set('data.title', 'Escape check')
        ->set('data.slug', 'escape-check')
        ->set('data.meta_title', 'Tips & tricks "quoted"')
        ->set('data.meta_description', '" onload="alert(1)<script>alert(2)</script>')
        ->set('data.is_published', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->withSession(['locale' => 'ru'])->get('/escape-check')
        ->assertOk()
        ->assertDontSee('<script>alert(2)</script>', false)
        ->assertSee('<title>Tips &amp; tricks &quot;quoted&quot;</title>', false)
        ->assertSee('<meta name="description" content="&quot; onload=&quot;alert(1)&lt;script&gt;alert(2)&lt;/script&gt;">', false);
});

// ------------------------------------------------------------------
// Reserved-prefix validation (audit §2.4 / §5.2)
// ------------------------------------------------------------------

it('rejects a slug with a reserved livewire- prefix', function () {
    $editor = hardeningUser('editor');

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Reserved prefix')
        ->set('data.slug', 'livewire-account')
        ->call('save')
        ->assertHasErrors(['data.slug' => __('filament.page_editor.validation.slug_reserved')]);

    expect(Page::query()->where('slug', 'livewire-account')->exists())->toBeFalse();
});

it('serves a page with a reserved prefix slug as 404', function () {
    // Written straight to the database: the public route must guard itself
    // even if a row escapes the editor validation.
    $page = hardeningPage(['slug' => 'livewire-files', 'is_published' => true]);

    $this->get('/livewire-files')->assertNotFound();

    // Control: the guard blocks the prefix, not the catch-all route itself.
    $page->update(['slug' => 'files-page']);

    $this->get('/files-page')->assertOk();
});

it('caps an autogenerated slug at the column width', function () {
    $title = str_repeat('long ', 60);

    $slug = Page::generateUniqueSlug($title);

    expect($slug)->not->toBe('')
        ->and(strlen($slug))->toBeLessThanOrEqual(Page::SLUG_MAX_LENGTH);
});

// ------------------------------------------------------------------
// Auto-generation safety (audit §2.4 / §5.3)
// ------------------------------------------------------------------

it('generates a safe slug for a reserved title such as Contacts', function () {
    $editor = hardeningUser('editor');

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Contacts')
        ->set('data.slug', '')
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::query()->where('title', 'Contacts')->latest()->firstOrFail();

    expect($page->slug)->not->toBe('contacts')
        ->and($page->slug)->toBeString()
        ->and($page->slug)->toMatch('/^page-contacts/');
});

it('generates a safe slug for a reserved title such as Login', function () {
    $editor = hardeningUser('editor');

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Login')
        ->set('data.slug', '')
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::query()->where('title', 'Login')->latest()->firstOrFail();

    expect($page->slug)->not->toBe('login')
        ->and($page->slug)->toMatch('/^page-login/');
});

it('generates unique slugs when two pages share the same title', function () {
    $editor = hardeningUser('editor');

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Team Rules')
        ->set('data.slug', '')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Team Rules')
        ->set('data.slug', '')
        ->call('save')
        ->assertHasNoErrors();

    $slugs = Page::query()->where('title', 'Team Rules')->pluck('slug')->toArray();

    expect($slugs)->toHaveCount(2)
        ->and($slugs[0])->not->toBe($slugs[1]);
});

it('reuses a slug after the page is soft deleted', function () {
    $editor = hardeningUser('editor');
    $page = hardeningPage(['slug' => 'reuse-test', 'title' => 'Old']);
    $page->delete();

    Livewire::actingAs($editor)->test(CreatePage::class)
        ->set('data.title', 'Reuse test')
        ->set('data.slug', '')
        ->call('save')
        ->assertHasNoErrors();

    // The generated slug is the freed one, not a suffixed variant.
    $fresh = Page::query()->where('title', 'Reuse test')->firstOrFail();

    expect($fresh->slug)->toBe('reuse-test')
        ->and(Page::query()->count())->toBe(1)
        ->and(Page::withTrashed()->where('slug', 'reuse-test')->count())->toBe(2);
});

// ------------------------------------------------------------------
// Orphan cleanup on non-image failure (audit §2.6 / §5.4)
// ------------------------------------------------------------------

it('removes uploaded files when resolveImages throws a non-image exception', function () {
    Storage::fake('public');

    $admin = hardeningUser('admin');
    $component = Livewire::actingAs($admin)
        ->test(EditPage::class, ['record' => 'sys.home'])
        ->instance();

    // Fake profile: first slot uploads, second references a missing constant.
    $fakeProfile = [
        'imageSlots' => [
            [
                'field' => 'hero_logo',
                'slot' => 'hero',
                'pathKey' => 'KEY_HERO_LOGO',
                'widthKey' => null,
                'heightKey' => null,
                'resetField' => 'reset_hero_logo',
            ],
            [
                'field' => 'main_image',
                'slot' => 'image',
                'pathKey' => 'KEY_DOES_NOT_EXIST',
                'widthKey' => null,
                'heightKey' => null,
                'resetField' => 'reset_main_image',
            ],
        ],
    ];

    $data = [
        'hero_logo' => UploadedFile::fake()->image('hero.jpg', 100, 100),
        'main_image' => null,
        'reset_hero_logo' => false,
        'reset_main_image' => false,
    ];

    $method = new ReflectionMethod($component, 'resolveImages');
    $method->setAccessible(true);

    expect(fn () => $method->invoke(
        $component,
        $fakeProfile,
        HomePageContent::class,
        $data,
        app(SettingsRepository::class),
        app(HomeImageUploader::class),
    ))->toThrow(RuntimeException::class);

    // The hero file must be cleaned up — no orphan.
    expect(Storage::disk('public')->files('home/hero'))->toBeEmpty();
});
