<?php

declare(strict_types=1);

use App\Models\News;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function makeSiteNews(array $overrides = []): News
{
    $author = User::factory()->create();
    $author->assignRole('admin');

    return News::create(array_merge([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Site news '.uniqid(),
        'slug' => 'site-news-'.uniqid(),
        'body' => 'Body text for the site news item.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
    ], $overrides));
}

beforeEach(function () {
    Cache::flush();
});

// ------------------------------------------------------------------
// Feed — §9.9
// ------------------------------------------------------------------

it('excludes archived news from the public feed', function () {
    makeSiteNews(['title' => 'Published story']);
    makeSiteNews([
        'title' => 'Archived story',
        'status' => News::STATUS_ARCHIVED,
    ]);

    get(route('news.index'))
        ->assertOk()
        ->assertSee('Published story')
        ->assertDontSee('Archived story');
});

it('shows published news in the public feed', function () {
    makeSiteNews(['title' => 'Fresh news']);

    get(route('news.index'))
        ->assertOk()
        ->assertSee('Fresh news');
});

// ------------------------------------------------------------------
// Direct URL — §9.9
// ------------------------------------------------------------------

it('returns 404 for guest accessing archived news by slug', function () {
    $archived = makeSiteNews([
        'title' => 'Old archived',
        'status' => News::STATUS_ARCHIVED,
    ]);

    get(route('news.show', $archived->slug))->assertNotFound();
});

it('returns 404 for regular user accessing archived news by slug', function () {
    $archived = makeSiteNews([
        'title' => 'Old archived',
        'status' => News::STATUS_ARCHIVED,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    actingAs($user)
        ->get(route('news.show', $archived->slug))
        ->assertNotFound();
});

it('allows editor to access archived news by slug', function () {
    $archived = makeSiteNews([
        'title' => 'Old archived',
        'status' => News::STATUS_ARCHIVED,
    ]);

    $editor = User::factory()->create(['email_verified_at' => now()]);
    $editor->assignRole('editor');

    actingAs($editor)
        ->get(route('news.show', $archived->slug))
        ->assertOk()
        ->assertSee('Old archived');
});
