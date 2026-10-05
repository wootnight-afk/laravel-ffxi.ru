<?php

declare(strict_types=1);

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Filament\Resources\NewsResource\Pages\ListNews;
use App\Models\News;
use App\Models\User;
use Livewire\Livewire;

function makeNewsPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeNewsRecord(User $author, array $overrides = []): News
{
    return News::create(array_merge([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'News '.uniqid(),
        'slug' => 'news-'.uniqid(),
        'body' => 'Body',
        'status' => News::STATUS_DRAFT,
    ], $overrides));
}

// ------------------------------------------------------------------
// Access
// ------------------------------------------------------------------

it('allows editors to access the news resource', function () {
    $editor = makeNewsPanelUser('editor');

    $this->actingAs($editor)->get('/admin/news')->assertOk();
});

it('denies regular users access to the news resource', function () {
    $user = makeNewsPanelUser('user');

    $this->actingAs($user)->get('/admin/news')->assertForbidden();
});

it('denies editors access to the create page when they cannot manage site news', function () {
    expect(NewsResource::canCreate())->toBeFalse();
});

// ------------------------------------------------------------------
// Markdown pipeline
// ------------------------------------------------------------------

it('stores the submitted Markdown body and renders sanitized HTML through the pipeline', function () {
    $admin = makeNewsPanelUser('admin');

    Livewire::actingAs($admin)
        ->test(CreateNews::class)
        ->set('data.title', 'Pipeline news')
        ->set('data.scope', News::SCOPE_SITE)
        ->set('data.user_id', $admin->id)
        ->set('data.body', "**Bold** text\n\n<script>alert(1)</script>")
        ->set('data.status', News::STATUS_DRAFT)
        ->call('create')
        ->assertHasNoErrors();

    $news = News::query()->latest('id')->firstOrFail();

    expect($news->body)->toBe("**Bold** text\n\n<script>alert(1)</script>")
        ->and($news->body_html)->toContain('<strong>Bold</strong>')
        ->and($news->body_html)->not->toContain('<script');
});

it('strips unsafe image and event-handler markup from the rendered body', function () {
    $admin = makeNewsPanelUser('admin');

    Livewire::actingAs($admin)
        ->test(CreateNews::class)
        ->set('data.title', 'XSS news')
        ->set('data.scope', News::SCOPE_SITE)
        ->set('data.user_id', $admin->id)
        ->set('data.body', '<img src=x onerror=alert(1)> <a href="javascript:alert(1)">x</a>')
        ->set('data.status', News::STATUS_DRAFT)
        ->call('create')
        ->assertHasNoErrors();

    $news = News::query()->latest('id')->firstOrFail();

    expect($news->body_html)->not->toContain('onerror')
        ->and($news->body_html)->not->toContain('javascript:');
});

// ------------------------------------------------------------------
// Author assignment
// ------------------------------------------------------------------

it('assigns the acting editor as the author when a non-admin creates news', function () {
    $editor = makeNewsPanelUser('editor');

    Livewire::actingAs($editor)
        ->test(CreateNews::class)
        ->set('data.title', 'Editor news')
        ->set('data.scope', News::SCOPE_SITE)
        ->set('data.body', 'Editor body')
        ->set('data.status', News::STATUS_DRAFT)
        ->call('create')
        ->assertHasNoErrors();

    $news = News::query()->latest('id')->firstOrFail();

    expect($news->user_id)->toBe($editor->id);
});

it('lets an admin choose a different author', function () {
    $admin = makeNewsPanelUser('admin');
    $author = makeNewsPanelUser('user');

    Livewire::actingAs($admin)
        ->test(CreateNews::class)
        ->set('data.title', 'Admin news')
        ->set('data.scope', News::SCOPE_SITE)
        ->set('data.body', 'Admin body')
        ->set('data.user_id', $author->id)
        ->set('data.status', News::STATUS_DRAFT)
        ->call('create')
        ->assertHasNoErrors();

    $news = News::query()->latest('id')->firstOrFail();

    expect($news->user_id)->toBe($author->id);
});

// ------------------------------------------------------------------
// Moderation actions
// ------------------------------------------------------------------

it('publishes a news record through the table action', function () {
    $admin = makeNewsPanelUser('admin');
    $news = makeNewsRecord($admin, ['status' => News::STATUS_DRAFT, 'published_at' => null]);

    Livewire::actingAs($admin)
        ->test(ListNews::class)
        ->callTableAction('publish', $news)
        ->assertHasNoTableActionErrors();

    $news->refresh();

    expect($news->status)->toBe(News::STATUS_PUBLISHED)
        ->and($news->published_at)->not->toBeNull();
});

it('rejects a news record with a required reason', function () {
    $admin = makeNewsPanelUser('admin');
    $news = makeNewsRecord($admin, ['status' => News::STATUS_PENDING]);

    Livewire::actingAs($admin)
        ->test(ListNews::class)
        ->mountTableAction('reject', $news)
        ->set('mountedActions.0.data.rejection_reason', 'Not suitable')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $news->refresh();

    expect($news->status)->toBe(News::STATUS_REJECTED)
        ->and($news->rejection_reason)->toBe('Not suitable');
});

it('does not reject a news record without a reason', function () {
    $admin = makeNewsPanelUser('admin');
    $news = makeNewsRecord($admin, ['status' => News::STATUS_PENDING]);

    Livewire::actingAs($admin)
        ->test(ListNews::class)
        ->mountTableAction('reject', $news)
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['rejection_reason']);

    expect($news->refresh()->status)->toBe(News::STATUS_PENDING);
});

it('archives a news record through the table action', function () {
    $admin = makeNewsPanelUser('admin');
    $news = makeNewsRecord($admin, ['status' => News::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);

    Livewire::actingAs($admin)
        ->test(ListNews::class)
        ->callTableAction('archive', $news)
        ->assertHasNoTableActionErrors();

    expect($news->refresh()->status)->toBe(News::STATUS_ARCHIVED);
});

it('publishes selected records in bulk', function () {
    $admin = makeNewsPanelUser('admin');
    $first = makeNewsRecord($admin, ['status' => News::STATUS_DRAFT]);
    $second = makeNewsRecord($admin, ['status' => News::STATUS_DRAFT]);

    Livewire::actingAs($admin)
        ->test(ListNews::class)
        ->callTableBulkAction('publish', [$first, $second])
        ->assertHasNoTableBulkActionErrors();

    expect($first->refresh()->status)->toBe(News::STATUS_PUBLISHED)
        ->and($second->refresh()->status)->toBe(News::STATUS_PUBLISHED);
});

it('does not expose an export action', function () {
    $admin = makeNewsPanelUser('admin');

    Livewire::actingAs($admin)
        ->test(ListNews::class)
        ->assertTableActionDoesNotExist('export')
        ->assertTableBulkActionDoesNotExist('export');
});
