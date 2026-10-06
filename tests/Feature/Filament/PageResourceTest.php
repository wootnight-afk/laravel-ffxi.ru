<?php

declare(strict_types=1);

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
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
        ->call('create')
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
