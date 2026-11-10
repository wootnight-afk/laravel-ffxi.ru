<?php

declare(strict_types=1);

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\AdminAuditLog;
use App\Models\Album;
use App\Models\News;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContactsPageContent;
use App\Services\EventsCreatePageContent;
use App\Services\GalleryPageContent;
use App\Services\HomePageContent;
use App\Services\NavigationSettingsConverter;
use App\Services\NewsPageContent;
use App\Services\PageMenuManager;
use App\Services\SettingsRepository;
use App\Services\SiteNavigation;
use App\Services\SitePageRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makePagePanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makePageRecord(array $overrides = []): Page
{
    return Page::create(array_merge([
        'title' => 'Page '.uniqid(),
        'slug' => 'page-'.uniqid(),
        'body' => 'Body',
        'is_published' => false,
        'show_in_menu' => false,
        'menu_order' => 0,
    ], $overrides));
}

// ------------------------------------------------------------------
// Access
// ------------------------------------------------------------------

it('allows editors to access the pages resource', function () {
    $editor = makePagePanelUser('editor');

    $this->actingAs($editor)->get('/admin/pages')->assertOk();
});

it('denies regular users access to the pages resource', function () {
    $user = makePagePanelUser('user');

    $this->actingAs($user)->get('/admin/pages')->assertForbidden();
});

// ------------------------------------------------------------------
// Markdown pipeline
// ------------------------------------------------------------------

it('stores the Markdown body and renders sanitized HTML through the pipeline', function () {
    $editor = makePagePanelUser('editor');

    Livewire::actingAs($editor)
        ->test(CreatePage::class)
        ->set('data.title', 'About us')
        ->set('data.body', "**Bold** text\n\n<script>alert(1)</script>")
        ->set('data.is_published', true)
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::query()->latest('id')->firstOrFail();

    expect($page->body)->toBe("**Bold** text\n\n<script>alert(1)</script>")
        ->and($page->body_html)->toContain('<strong>Bold</strong>')
        ->and($page->body_html)->not->toContain('<script');
});

// ------------------------------------------------------------------
// Menu and meta fields
// ------------------------------------------------------------------

it('stores menu settings and meta fields on update', function () {
    $editor = makePagePanelUser('editor');
    $page = makePageRecord();

    Livewire::actingAs($editor)
        ->test(EditPage::class, ['record' => $page->getKey()])
        ->set('data.show_in_menu', true)
        ->set('data.menu_order', 7)
        ->set('data.meta_title', 'About meta title')
        ->set('data.meta_description', 'About meta description')
        ->call('save')
        ->assertHasNoErrors();

    $page->refresh();

    expect($page->show_in_menu)->toBeTrue()
        ->and($page->menu_order)->toBe(7)
        ->and($page->meta_title)->toBe('About meta title')
        ->and($page->meta_description)->toBe('About meta description');
});

// ------------------------------------------------------------------
// Publication
// ------------------------------------------------------------------

it('publishes and unpublishes a page through table actions', function () {
    $editor = makePagePanelUser('editor');
    $page = makePageRecord(['is_published' => false]);

    Livewire::actingAs($editor)
        ->test(ListPages::class)
        ->callTableAction('publish', $page)
        ->assertHasNoTableActionErrors();

    expect($page->refresh()->is_published)->toBeTrue();

    Livewire::actingAs($editor)
        ->test(ListPages::class)
        ->callTableAction('unpublish', $page)
        ->assertHasNoTableActionErrors();

    expect($page->refresh()->is_published)->toBeFalse();
});

it('saves both explicit menu bindings and retains metadata when hidden', function () {
    $page = makePageRecord();
    $manager = app(PageMenuManager::class);
    $manager->save($page, ['show_in_menu' => true, 'show_in_footer' => true]);
    $settings = app(SettingsRepository::class);
    $header = $settings->get(SiteNavigation::KEY_HEADER_ITEMS);
    $binding = end($header);

    expect($binding['target'])->toBe((string) $page->getKey())
        ->and($binding)->not->toHaveKey('visible')
        ->and($settings->get(SiteNavigation::KEY_FOOTER_ITEMS))->toHaveCount(3);

    $manager->save($page, ['title' => 'Changed title', 'show_in_menu' => false, 'show_in_footer' => false]);
    expect($settings->get(SiteNavigation::KEY_HEADER_ITEMS))->toBe($header)
        ->and($page->refresh()->show_in_footer)->toBeFalse();
});

it('rolls back all cms fields menus and audit when saving a menu fails', function () {
    $editor = makePagePanelUser('editor');
    $page = makePageRecord();
    $old = $page->refresh()->getAttributes();
    $settings = Setting::query()->orderBy('key')->get()->toArray();
    $auditCount = AdminAuditLog::count();
    $event = 'eloquent.saving: '.Setting::class;
    Event::listen($event, function (Setting $setting): void {
        if ($setting->key === SiteNavigation::KEY_FOOTER_ITEMS) {
            throw new RuntimeException('Injected menu failure');
        }
    });

    try {
        expect(fn () => Livewire::actingAs($editor)
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->set('data.title', 'Changed')
            ->set('data.body_en', 'English body')
            ->set('data.show_in_menu', true)
            ->set('data.show_in_footer', true)
            ->call('save'))->toThrow(RuntimeException::class, 'Injected menu failure');
    } finally {
        Event::forget($event);
    }

    expect($page->refresh()->getAttributes())->toBe($old)
        ->and(Setting::query()->orderBy('key')->get()->toArray())->toBe($settings)
        ->and(AdminAuditLog::count())->toBe($auditCount);
});

it('deletes cms pages and both bindings together through the authorized action', function () {
    $editor = makePagePanelUser('editor');
    $page = makePageRecord();
    app(PageMenuManager::class)->save($page, ['show_in_menu' => true, 'show_in_footer' => true]);

    Livewire::actingAs($editor)->test(EditPage::class, ['record' => $page->getKey()])
        ->callAction('delete')->assertHasNoActionErrors();

    expect($page->refresh()->trashed())->toBeTrue()
        ->and(AdminAuditLog::where('action', 'page.deleted')->where('subject_id', $page->getKey())->count())->toBe(1);
    foreach ([SiteNavigation::KEY_HEADER_ITEMS, SiteNavigation::KEY_FOOTER_ITEMS] as $key) {
        expect(collect(app(SettingsRepository::class)->get($key))->where('type', 'page')->all())->toBe([]);
    }
});

it('rolls back deletion and binding removal when audit fails', function () {
    $page = makePageRecord();
    $manager = app(PageMenuManager::class);
    $manager->save($page, ['show_in_menu' => true, 'show_in_footer' => true]);
    $settings = Setting::query()->orderBy('key')->get()->toArray();
    $auditCount = AdminAuditLog::count();
    $event = 'eloquent.creating: '.AdminAuditLog::class;
    Event::listen($event, function (AdminAuditLog $log): void {
        if ($log->action === 'page.deleted') {
            throw new RuntimeException('Injected audit failure');
        }
    });

    try {
        expect(fn () => $manager->delete($page))->toThrow(RuntimeException::class, 'Injected audit failure');
    } finally {
        Event::forget($event);
    }

    expect($page->refresh()->trashed())->toBeFalse()
        ->and(Setting::query()->orderBy('key')->get()->toArray())->toBe($settings)
        ->and(AdminAuditLog::count())->toBe($auditCount);
});

it('previews and converts navigation idempotently preserving content and metadata', function () {
    Storage::fake('local');
    $page = makePageRecord(['is_published' => true, 'show_in_menu' => true]);
    $original = $page->refresh()->getAttributes();
    app(SettingsRepository::class)->set(SiteNavigation::KEY_FOOTER_ITEMS, [[
        'id' => 'footer-page', 'type' => 'page', 'target' => $page->slug,
        'label_ru' => 'Custom RU', 'label_en' => 'Custom EN', 'visible' => false,
        'order' => 12, 'image' => 'nav/keep.webp',
    ]]);
    $converter = app(NavigationSettingsConverter::class);
    $preview = $converter->preview();
    expect($preview['conflicts'])->toBe([])
        ->and(Setting::find(SiteNavigation::KEY_HEADER_ITEMS))->toBeNull();
    $result = $converter->apply($preview);
    expect($result['changed'])->toBeTrue();
    Storage::disk('local')->assertExists($result['snapshot_path']);
    $footer = app(SettingsRepository::class)->get(SiteNavigation::KEY_FOOTER_ITEMS)[0];
    expect($footer['target'])->toBe((string) $page->getKey())
        ->and($footer['label_en'])->toBe('Custom EN')
        ->and($footer['image'])->toBe('nav/keep.webp')
        ->and($footer['order'])->toBe(12)
        ->and($footer)->not->toHaveKey('visible')
        ->and($page->refresh()->getAttributes())->toBe($original);
    expect($converter->apply($converter->preview()))->toBe(['changed' => false, 'snapshot_path' => null]);
});

it('refuses changed snapshots and conflicting cms visibility without writes', function () {
    $page = makePageRecord(['is_published' => true, 'show_in_menu' => true]);
    $converter = app(NavigationSettingsConverter::class);
    $preview = $converter->preview();
    $page->update(['title' => 'Changed by owner']);
    expect(fn () => $converter->apply($preview))->toThrow(RuntimeException::class, 'snapshot changed');
    app(SettingsRepository::class)->set(SiteNavigation::KEY_HEADER_ITEMS, [[
        'id' => 'conflict', 'type' => 'page', 'target' => $page->slug, 'visible' => false,
    ]]);
    $before = Setting::query()->orderBy('key')->get()->toArray();
    $preview = $converter->preview();
    expect($preview['conflicts'])->not->toBe([]);
    expect(fn () => $converter->apply($preview))->toThrow(RuntimeException::class, 'conflicts');
    expect(Setting::query()->orderBy('key')->get()->toArray())->toBe($before);
});

it('includes drafts and all seven cabinet tabs while classifying technical routes', function () {
    $draft = makePageRecord();
    $registry = app(SitePageRegistry::class);
    $records = $registry->records();
    expect(collect($records)->filter(fn ($record): bool => $record instanceof Page && $record->is($draft)))->toHaveCount(1);
    $tabs = collect($records)->filter(fn ($record): bool => is_array($record) && $record['kind'] === 'cabinet')->pluck('url')->all();
    expect($tabs)->toBe([
        '/cabinet/profile', '/cabinet/social', '/cabinet/news', '/cabinet/gallery',
        '/cabinet/events', '/cabinet/security', '/cabinet/danger',
    ]);
    $routes = collect($registry->classification());
    expect($routes->firstWhere('name', 'verification.notice')['included'])->toBeTrue()
        ->and($routes->firstWhere('name', 'verification.verify')['included'])->toBeFalse()
        ->and($routes->firstWhere('name', 'locale.update')['included'])->toBeFalse()
        ->and($routes->where('reason', 'unclassified GET route: review required')->all())->toBe([]);
    Livewire::actingAs(makePagePanelUser('editor'))->test(ListPages::class)
        ->assertSee(__('filament.page_registry.labels.cabinet_events'))->assertSee($draft->title)
        ->assertTableActionHidden('delete', 'system:cabinet.tab.events')
        ->assertTableActionHidden('editor', 'system:cabinet.tab.events');
});

// ------------------------------------------------------------------
// System row editors (news, gallery, home)
// ------------------------------------------------------------------

it('edits the news and gallery headers from the unified page editor', function () {
    $admin = makePagePanelUser('admin');
    $editor = makePagePanelUser('editor');

    $this->actingAs($admin)->get('/admin/pages/sys.news.index/edit')->assertOk();
    $this->actingAs($admin)->get('/admin/pages/sys.gallery.index/edit')->assertOk();
    $this->actingAs($admin)->get('/admin/pages/sys.contacts/edit')->assertOk();
    $this->actingAs($admin)->get('/admin/pages/sys.events.index/edit')->assertOk();
    $this->actingAs($admin)->get('/admin/pages/sys.events.create/edit')->assertOk();
    $this->actingAs($admin)->get('/admin/pages/sys.home/edit')->assertOk();

    $this->actingAs($editor)->get('/admin/pages/sys.news.index/edit')->assertForbidden();
    $this->actingAs($editor)->get('/admin/pages/sys.gallery.index/edit')->assertForbidden();

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.news.index'])
        ->set('data.meta_title', 'News meta')
        ->set('data.title', 'News heading')
        ->set('data.body', 'Intro **text**')
        ->set('data.is_news_component_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsRepository::class);
    expect($settings->get(NewsPageContent::KEY_H1))->toBe('News heading')
        ->and($settings->get(NewsPageContent::KEY_IS_NEWS_COMPONENT_ENABLED))->toBeFalse();

    $author = User::factory()->create();
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Hidden feed item',
        'slug' => 'hidden-feed-item',
        'body' => 'A sufficiently detailed news body.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);

    $this->get('/news')
        ->assertOk()
        ->assertSee('News heading')
        ->assertSee('Intro <strong>text</strong>', false)
        ->assertDontSee($news->title);

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.news.index'])
        ->set('data.is_news_component_enabled', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->get('/news')->assertOk()->assertSee($news->title);

    $album = Album::create([
        'user_id' => $author->id,
        'scope' => Album::SCOPE_SITE,
        'title' => 'Hidden album',
        'slug' => 'hidden-album',
        'is_published' => true,
    ]);

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.gallery.index'])
        ->set('data.meta_title', 'Gallery meta')
        ->set('data.title', 'Gallery heading')
        ->set('data.body', 'Gallery intro')
        ->set('data.is_gallery_component_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($settings->get(GalleryPageContent::KEY_H1))->toBe('Gallery heading')
        ->and($settings->get(GalleryPageContent::KEY_IS_GALLERY_COMPONENT_ENABLED))->toBeFalse();

    $this->get('/gallery')
        ->assertOk()
        ->assertSee('Gallery heading')
        ->assertSee('Gallery intro')
        ->assertDontSee($album->title);
});

it('edits the contacts and events pages through the unified page editor', function () {
    $admin = makePagePanelUser('admin');
    $settings = app(SettingsRepository::class);

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.contacts'])
        ->set('data.title', 'Contact us')
        ->set('data.body', 'Write to **us**.')
        ->set('data.meta_title', 'Contacts meta')
        ->call('save')
        ->assertHasNoErrors();

    expect($settings->get(ContactsPageContent::KEY_H1))->toBe('Contact us')
        ->and($settings->get(ContactsPageContent::KEY_BODY))->toBe('Write to **us**.');

    $this->get('/contacts')
        ->assertOk()
        ->assertSee('Contact us')
        ->assertSee('Write to <strong>us</strong>.', false);

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.events.index'])
        ->set('data.title', 'Community events')
        ->call('save')
        ->assertHasNoErrors();

    $this->get('/events')->assertOk()->assertSee('Community events');

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.events.create'])
        ->set('data.title', 'Create a new event')
        ->call('save')
        ->assertHasNoErrors();

    expect($settings->get(EventsCreatePageContent::KEY_H1))->toBe('Create a new event');
});

it('disables unavailable fields for the edited page type', function () {
    $admin = makePagePanelUser('admin');

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.contacts'])
        ->assertFormFieldIsDisabled('slug')
        ->assertFormFieldIsDisabled('menu_order')
        ->assertFormFieldIsDisabled('hero_logo')
        ->assertFormFieldIsEnabled('title')
        ->assertFormFieldIsEnabled('show_in_menu')
        ->assertFormFieldIsEnabled('meta_title');
});

it('hides the home main image and body through component switches', function () {
    $admin = makePagePanelUser('admin');
    $settings = app(SettingsRepository::class);
    $settings->setMany([
        HomePageContent::KEY_H1 => 'Home heading',
        HomePageContent::KEY_BODY => 'Visible home body',
    ]);

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.home'])
        ->set('data.is_main_image_component_enabled', false)
        ->set('data.is_body_component_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($settings->get(HomePageContent::KEY_IS_MAIN_IMAGE_COMPONENT_ENABLED))->toBeFalse()
        ->and($settings->get(HomePageContent::KEY_IS_BODY_COMPONENT_ENABLED))->toBeFalse();

    $this->get('/')
        ->assertOk()
        ->assertSee('Home heading')
        ->assertDontSee('Visible home body')
        ->assertDontSee('class="home-image"', false);
});

// ------------------------------------------------------------------
// Export exclusion (R7)
// ------------------------------------------------------------------

it('does not expose an export action in the pages resource', function () {
    $editor = makePagePanelUser('editor');

    Livewire::actingAs($editor)
        ->test(ListPages::class)
        ->assertTableActionDoesNotExist('export')
        ->assertTableBulkActionDoesNotExist('export');
});

// ------------------------------------------------------------------
// Row actions visibility (sticky column fix)
// ------------------------------------------------------------------

it('keeps the cms row actions visible in the pages registry table', function () {
    $editor = makePagePanelUser('editor');
    $page = makePageRecord(['is_published' => true]);

    Livewire::actingAs($editor)
        ->test(ListPages::class)
        ->assertTableActionVisible('unpublish', $page)
        ->assertTableActionVisible('editor', $page)
        ->assertTableActionVisible('delete', $page);
});

it('pins the action column by loading the admin table stylesheet', function () {
    $editor = makePagePanelUser('editor');

    $this->actingAs($editor)
        ->get('/admin/pages')
        ->assertOk()
        ->assertSee('css/admin-tables.css', false);
});
