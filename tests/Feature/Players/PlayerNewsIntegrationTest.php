<?php

declare(strict_types=1);

use App\Models\News;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

function makeNewsAuthor(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'is_profile_public' => true,
    ], $overrides));
    $user->assignRole('user');

    return $user;
}

beforeEach(function () {
    Cache::flush();
    app(SettingsRepository::class)->set('player_news_moderation', 'post');
    app(SettingsRepository::class)->set('guest_sections', [
        'home' => true,
        'news' => true,
        'gallery' => true,
        'contacts' => true,
        'events' => true,
        'players' => false,
        'player_profiles' => false,
    ]);
});

it('does not show draft player news on a player page to other users', function () {
    $author = makeNewsAuthor();

    actingAs($author)
        ->post(route('cabinet.news.store'), [
            'title' => 'Draft story X',
            'body' => 'Body text long enough for validation.',
        ]);

    $viewer = makeNewsAuthor();

    actingAs($viewer)
        ->get(route('players.show', $author->name))
        ->assertOk()
        ->assertDontSee('Draft story X');
});

it('shows published player news on a player page to other users', function () {
    $author = makeNewsAuthor();

    actingAs($author)
        ->post(route('cabinet.news.store'), [
            'title' => 'Published story Y',
            'body' => 'Body text long enough for validation.',
        ]);

    $news = News::query()->where('title', 'Published story Y')->firstOrFail();

    actingAs($author)
        ->post(route('cabinet.news.publish', $news));

    $viewer = makeNewsAuthor();

    actingAs($viewer)
        ->get(route('players.show', $author->name))
        ->assertOk()
        ->assertSee('Published story Y');
});

it('hides all player news on a closed profile from other users', function () {
    $author = makeNewsAuthor();

    actingAs($author)
        ->post(route('cabinet.news.store'), [
            'title' => 'Closed profile story Z',
            'body' => 'Body text long enough for validation.',
        ]);

    $news = News::query()->where('title', 'Closed profile story Z')->firstOrFail();

    actingAs($author)
        ->post(route('cabinet.news.publish', $news));

    $author->forceFill(['is_profile_public' => false])->save();
    $viewer = makeNewsAuthor();

    actingAs($viewer)
        ->get(route('players.show', $author->name))
        ->assertOk()
        ->assertDontSee('Closed profile story Z');
});

it('moves player news to pending in pre moderation mode', function () {
    app(SettingsRepository::class)->set('player_news_moderation', 'pre');

    $author = makeNewsAuthor();

    actingAs($author)
        ->post(route('cabinet.news.store'), [
            'title' => 'Pending story P',
            'body' => 'Body text long enough for validation.',
        ]);

    $news = News::query()->where('title', 'Pending story P')->firstOrFail();

    actingAs($author)
        ->post(route('cabinet.news.publish', $news));

    expect($news->fresh()->status)->toBe(News::STATUS_PENDING);
    expect($news->fresh()->published_at)->toBeNull();
});

it('currently does not show pending player news to the author (known gap)', function () {
    app(SettingsRepository::class)->set('player_news_moderation', 'pre');
    $author = makeNewsAuthor();

    actingAs($author)
        ->post(route('cabinet.news.store'), [
            'title' => 'Author pending story Q',
            'body' => 'Body text long enough for validation.',
        ]);

    $news = News::query()->where('title', 'Author pending story Q')->firstOrFail();

    actingAs($author)
        ->post(route('cabinet.news.publish', $news));

    actingAs($author)
        ->get(route('players.show', $author->name))
        ->assertOk()
        ->assertDontSee('Author pending story Q');
});
