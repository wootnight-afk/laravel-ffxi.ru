<?php

declare(strict_types=1);

use App\Models\News;
use App\Models\User;

beforeEach(function () {
    app()->setLocale('ru');
});

function displayNews(User $author, string $scope, ?string $publishedAt): News
{
    return News::create([
        'user_id' => $author->id,
        'scope' => $scope,
        'title' => 'News '.uniqid(),
        'slug' => 'news-'.uniqid(),
        'body' => 'A sufficiently detailed news body.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => $publishedAt,
    ]);
}

it('never shifts the calendar date of site news', function () {
    $news = displayNews(User::factory()->create(), News::SCOPE_SITE, '2026-11-04 23:30:00');

    expect($news->displayPublishedAt('Europe/Berlin', 'ru'))->toBe('04.11.2026')
        ->and($news->displayPublishedAt('Asia/Tokyo', 'ru'))->toBe('04.11.2026')
        ->and($news->displayPublishedAt('Europe/Berlin', 'en'))->toBe('Nov 04, 2026');
});

it('converts the moment of player news to the viewer timezone', function () {
    $news = displayNews(User::factory()->create(), News::SCOPE_PLAYER, '2026-11-04 23:30:00');

    expect($news->displayPublishedAt('Europe/Berlin', 'ru'))->toBe('05.11.2026 00:30')
        ->and($news->displayPublishedAt('UTC', 'ru'))->toBe('04.11.2026 23:30')
        ->and($news->displayPublishedAt('Europe/Berlin', 'en'))->toBe('Nov 05, 2026 00:30');
});

it('renders a placeholder when the publication date is empty', function () {
    $news = displayNews(User::factory()->create(), News::SCOPE_SITE, null);

    expect($news->displayPublishedAt('Europe/Berlin', 'ru'))->toBe('—');
});

it('resolves the viewer timezone for player news by default', function () {
    $viewer = User::factory()->create(['timezone' => 'Europe/Berlin']);
    $this->actingAs($viewer);

    $news = displayNews($viewer, News::SCOPE_PLAYER, '2026-11-04 23:30:00');

    expect($news->displayPublishedAt())->toBe('05.11.2026 00:30');
});
