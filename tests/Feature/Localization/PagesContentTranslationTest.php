<?php

declare(strict_types=1);

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use App\Services\SettingsRepository;
use App\Services\SiteNavigation;
use Livewire\Livewire;

function makeLocalizedPage(array $overrides = []): Page
{
    return Page::create(array_merge([
        'title' => 'О сервере',
        'slug' => 'about-'.uniqid(),
        'body' => 'Русский **текст**.',
        'is_published' => true,
        'show_in_menu' => false,
        'menu_order' => 0,
    ], $overrides));
}

it('renders the english content of a translated page', function () {
    $page = makeLocalizedPage([
        'title_en' => 'About the server',
        'body_en' => 'English **text**.',
        'meta_title_en' => 'About meta title (EN)',
    ]);

    $this->withSession(['locale' => 'en'])
        ->get(route('page.show', ['page' => $page->slug]))
        ->assertOk()
        ->assertSee('About the server')
        ->assertSee('<strong>text</strong>', false)
        ->assertSee('About meta title (EN)')
        ->assertDontSee('Русский');
});

it('falls back per field when the english translation is partial', function () {
    $page = makeLocalizedPage([
        'title_en' => 'About the server',
        'body_en' => null,
    ]);

    $this->withSession(['locale' => 'en'])
        ->get(route('page.show', ['page' => $page->slug]))
        ->assertOk()
        ->assertSee('About the server')
        // The body falls back to the Russian HTML.
        ->assertSee('<strong>текст</strong>', false);
});

it('keeps a page without any english translation visible and rendered', function () {
    $page = makeLocalizedPage(['show_in_menu' => true]);

    $this->withSession(['locale' => 'en'])
        ->get(route('page.show', ['page' => $page->slug]))
        ->assertOk()
        ->assertSee('О сервере')
        ->assertSee('<strong>текст</strong>', false);

    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        // CMS pages are linked only through an explicit header binding.
        ->assertDontSee(route('page.show', ['page' => $page->slug]));

    app(SettingsRepository::class)->set(SiteNavigation::KEY_HEADER_ITEMS, [[
        'id' => 'page-'.$page->id,
        'type' => SiteNavigation::TYPE_PAGE,
        'target' => (string) $page->id,
        'label_ru' => $page->title,
        'label_en' => '',
        'order' => 0,
    ]]);

    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('page.show', ['page' => $page->slug]));
});

it('renders the russian content in the russian locale', function () {
    $page = makeLocalizedPage([
        'title_en' => 'About the server',
        'body_en' => 'English text.',
    ]);

    $this->withSession(['locale' => 'ru'])
        ->get(route('page.show', ['page' => $page->slug]))
        ->assertOk()
        ->assertSee('О сервере')
        ->assertSee('<strong>текст</strong>', false)
        ->assertDontSee('About the server');
});

it('renders the localized meta description when present', function () {
    $page = makeLocalizedPage([
        'meta_description' => 'Русское описание.',
        'meta_description_en' => 'English description.',
    ]);

    $this->withSession(['locale' => 'en'])
        ->get(route('page.show', ['page' => $page->slug]))
        ->assertOk()
        ->assertSee('<meta name="description" content="English description.">', false);

    $this->withSession(['locale' => 'ru'])
        ->get(route('page.show', ['page' => $page->slug]))
        ->assertOk()
        ->assertSee('<meta name="description" content="Русское описание.">', false);
});

it('saves the english columns through the admin page and renders the html', function () {
    $editor = User::factory()->create(['email_verified_at' => now()]);
    $editor->assignRole('editor');

    $page = makeLocalizedPage();

    Livewire::actingAs($editor)
        ->test(EditPage::class, ['record' => $page->getKey()])
        ->set('data.title_en', 'About the server')
        ->set('data.body_en', "English **text**\n\n<script>alert(1)</script>")
        ->set('data.meta_title_en', 'About meta title (EN)')
        ->set('data.meta_description_en', 'English description.')
        ->call('save')
        ->assertHasNoErrors();

    $page->refresh();

    expect($page->title)->toBe('О сервере')
        ->and($page->title_en)->toBe('About the server')
        ->and($page->body)->toBe('Русский **текст**.')
        ->and($page->body_html_en)->toContain('<strong>text</strong>')
        ->and($page->body_html_en)->not->toContain('<script')
        ->and($page->meta_title_en)->toBe('About meta title (EN)')
        ->and($page->meta_description_en)->toBe('English description.');
});
