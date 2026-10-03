<?php

declare(strict_types=1);

use App\Models\News;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;

function makeMyNewsUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function makeDraftNews(User $user, array $overrides = []): News
{
    return News::create(array_merge([
        'user_id' => $user->id,
        'scope' => News::SCOPE_PLAYER,
        'title' => 'Черновик',
        'body' => 'Текст черновика достаточно длинный для валидации.',
        'status' => News::STATUS_DRAFT,
    ], $overrides));
}

function makeCoverJpeg(int $width = 800, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));

    $path = tempnam(sys_get_temp_dir(), 'cover_').'.jpg';
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return $path;
}

beforeEach(function () {
    Cache::flush();
    Storage::fake('public');
    app(SettingsRepository::class)->set('player_news_moderation', 'post');
});

// ------------------------------------------------------------------
// Access barriers
// ------------------------------------------------------------------

it('redirects guests from my-news actions', function () {
    post(route('cabinet.news.store'), [
        'title' => 'x',
        'body' => 'y',
    ])->assertRedirect(route('login'));
});

it('blocks unverified user from creating news', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    actingAs($user)
        ->post(route('cabinet.news.store'), [
            'title' => 'Test title',
            'body' => 'Body text that is long enough.',
        ])
        ->assertRedirect(route('verification.notice'));
});

it('forbids users without news.create_own permission', function () {
    $user = User::factory()->create();
    // no role assigned

    actingAs($user)
        ->post(route('cabinet.news.store'), [
            'title' => 'Test title',
            'body' => 'Body text that is long enough.',
        ])
        ->assertForbidden();
});

// ------------------------------------------------------------------
// Create (always draft)
// ------------------------------------------------------------------

it('creates a draft', function () {
    $user = makeMyNewsUser();

    actingAs($user)
        ->post(route('cabinet.news.store'), [
            'title' => 'Мой заголовок',
            'body' => 'Достаточно длинный текст новости для валидации.',
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    $news = News::where('user_id', $user->id)->first();
    expect($news)->not->toBeNull();
    expect($news->status)->toBe(News::STATUS_DRAFT);
    expect($news->scope)->toBe(News::SCOPE_PLAYER);
    expect($news->published_at)->toBeNull();
});

it('rejects too short title', function () {
    $user = makeMyNewsUser();

    actingAs($user)
        ->post(route('cabinet.news.store'), [
            'title' => 'x',
            'body' => 'Достаточно длинный текст для валидации.',
        ])
        ->assertSessionHasErrors('title');
});

it('rejects too short body', function () {
    $user = makeMyNewsUser();

    actingAs($user)
        ->post(route('cabinet.news.store'), [
            'title' => 'Заголовок',
            'body' => 'short',
        ])
        ->assertSessionHasErrors('body');
});

// ------------------------------------------------------------------
// Update — allowed for draft/pending/rejected
// ------------------------------------------------------------------

it('updates a draft', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user);

    actingAs($user)
        ->patch(route('cabinet.news.update', $news), [
            'title' => 'Новый заголовок',
            'body' => 'Новый текст новости достаточной длины.',
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    $news->refresh();
    expect($news->title)->toBe('Новый заголовок');
});

it('allows editing a rejected news', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user, [
        'status' => News::STATUS_REJECTED,
        'rejection_reason' => 'Плохо',
    ]);

    actingAs($user)
        ->patch(route('cabinet.news.update', $news), [
            'title' => 'Исправлено',
            'body' => 'Исправленный текст с достаточной длиной.',
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    expect($news->fresh()->title)->toBe('Исправлено');
});

it('allows editing a pending news', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user, ['status' => News::STATUS_PENDING]);

    actingAs($user)
        ->patch(route('cabinet.news.update', $news), [
            'title' => 'Обновлено',
            'body' => 'Обновлённый текст с достаточной длиной.',
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));
});

it('forbids editing a published news', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user, [
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    actingAs($user)
        ->patch(route('cabinet.news.update', $news), [
            'title' => 'Поздняя правка',
            'body' => 'Текст с достаточной длиной для валидации.',
        ])
        ->assertForbidden();
});

it('forbids editing a foreign news', function () {
    $owner = makeMyNewsUser();
    $attacker = makeMyNewsUser();
    $news = makeDraftNews($owner);

    actingAs($attacker)
        ->patch(route('cabinet.news.update', $news), [
            'title' => 'Взлом',
            'body' => 'Текст с достаточной длиной для валидации.',
        ])
        ->assertForbidden();
});

// ------------------------------------------------------------------
// Publish — post mode
// ------------------------------------------------------------------

it('publishes immediately in post mode', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user);

    actingAs($user)
        ->post(route('cabinet.news.publish', $news))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    $news->refresh();
    expect($news->status)->toBe(News::STATUS_PUBLISHED);
    expect($news->published_at)->not->toBeNull();
});

it('publishes via update with status=published in post mode', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user);

    actingAs($user)
        ->patch(route('cabinet.news.update', $news), [
            'title' => $news->title,
            'body' => $news->body,
            'status' => 'published',
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    $news->refresh();
    expect($news->status)->toBe(News::STATUS_PUBLISHED);
    expect($news->published_at)->not->toBeNull();
});

// ------------------------------------------------------------------
// Publish — pre mode
// ------------------------------------------------------------------

it('moves to pending in pre mode', function () {
    app(SettingsRepository::class)->set('player_news_moderation', 'pre');

    $user = makeMyNewsUser();
    $news = makeDraftNews($user);

    actingAs($user)
        ->post(route('cabinet.news.publish', $news))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    $news->refresh();
    expect($news->status)->toBe(News::STATUS_PENDING);
    expect($news->published_at)->toBeNull();
});

// ------------------------------------------------------------------
// Delete
// ------------------------------------------------------------------

it('deletes own draft', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user);

    actingAs($user)
        ->delete(route('cabinet.news.destroy', $news))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    expect(News::find($news->id))->toBeNull();
});

it('forbids deleting foreign news', function () {
    $owner = makeMyNewsUser();
    $attacker = makeMyNewsUser();
    $news = makeDraftNews($owner);

    actingAs($attacker)
        ->delete(route('cabinet.news.destroy', $news))
        ->assertForbidden();

    expect(News::find($news->id))->not->toBeNull();
});

// ------------------------------------------------------------------
// Cover
// ------------------------------------------------------------------

it('uploads a cover and creates webp', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user);

    $path = makeCoverJpeg();
    $file = new UploadedFile($path, 'cover.jpg', 'image/jpeg', null, true);

    actingAs($user)
        ->post(route('cabinet.news.cover', $news), ['cover' => $file])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    $news->refresh();
    expect($news->cover_path)->not->toBeNull();
    expect($news->cover_path)->toEndWith('_cover.webp');
    Storage::disk('public')->assertExists($news->cover_path);

    @unlink($path);
});

it('rejects non-image cover', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user);

    $path = tempnam(sys_get_temp_dir(), 'txt_').'.txt';
    file_put_contents($path, 'not an image');
    $file = new UploadedFile($path, 'cover.txt', 'text/plain', null, true);

    actingAs($user)
        ->post(route('cabinet.news.cover', $news), ['cover' => $file])
        ->assertSessionHasErrors('cover');

    @unlink($path);
});

it('deletes a cover', function () {
    $user = makeMyNewsUser();
    $news = makeDraftNews($user);

    $path = makeCoverJpeg();
    $file = new UploadedFile($path, 'cover.jpg', 'image/jpeg', null, true);
    actingAs($user)->post(route('cabinet.news.cover', $news), ['cover' => $file]);
    $news->refresh();

    $oldPath = $news->cover_path;
    expect($oldPath)->not->toBeNull();

    actingAs($user)
        ->delete(route('cabinet.news.cover.delete', $news))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'news']));

    $news->refresh();
    expect($news->cover_path)->toBeNull();
    Storage::disk('public')->assertMissing($oldPath);

    @unlink($path);
});
