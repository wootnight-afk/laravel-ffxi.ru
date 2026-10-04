<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\News;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function stage7AcceptanceUser(string $role = 'user'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function stage7AcceptanceNews(User $author): News
{
    return News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Stage 7 acceptance news '.Str::random(8),
        'body' => 'News body for the Stage 7 acceptance test.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
        'comments_enabled' => true,
    ]);
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Notification::fake();
});

it('records activity for approved comments but not comments pending moderation', function () {
    $author = stage7AcceptanceUser('admin');
    $news = stage7AcceptanceNews($author);
    $commenter = stage7AcceptanceUser();
    $approvedBody = 'Approved acceptance comment with enough characters.';
    $pendingBody = 'Pending acceptance comment with enough characters.';

    app(SettingsRepository::class)->set('comments_moderation', 'none');
    $approvedResponse = $this->actingAs($commenter)
        ->postJson(route('comments.store', $news->slug), ['body' => $approvedBody])
        ->assertCreated()
        ->assertJsonPath('status', 'approved');
    $approvedCommentId = $approvedResponse->json('id');

    expect(Activity::query()
        ->where('type', ActivityType::CommentCreated->value)
        ->where('actor_id', $commenter->id)
        ->where('subject_type', (new Comment)->getMorphClass())
        ->where('subject_id', $approvedCommentId)
        ->count())->toBe(1);

    app(SettingsRepository::class)->set('comments_moderation', 'all');
    $pendingResponse = $this->postJson(route('comments.store', $news->slug), ['body' => $pendingBody])
        ->assertCreated()
        ->assertJsonPath('status', 'pending');
    $pendingCommentId = $pendingResponse->json('id');

    expect(Activity::query()
        ->where('type', ActivityType::CommentCreated->value)
        ->where('actor_id', $commenter->id)
        ->where('subject_type', (new Comment)->getMorphClass())
        ->where('subject_id', $pendingCommentId)
        ->exists())->toBeFalse();
});

it('does not expose an inaccessible player-news snapshot or link in the dashboard bell', function () {
    $author = stage7AcceptanceUser();
    $author->forceFill(['is_profile_public' => false])->saveQuietly();
    $title = 'Private acceptance snapshot '.Str::random(10);
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_PLAYER,
        'title' => $title,
        'body' => 'A private-profile player news body.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);
    Activity::query()->create([
        'actor_id' => $author->id,
        'type' => ActivityType::NewsPublished,
        'subject_type' => News::class,
        'subject_id' => $news->id,
        'data' => ['title' => $title, 'scope' => 'player'],
        'created_at' => now(),
    ]);
    $viewer = stage7AcceptanceUser();
    $response = $this->actingAs($viewer)->get(route('players.dashboard'))->assertOk();

    $response->assertDontSee($title)
        ->assertDontSee(route('news.show', $news->slug), false)
        ->assertDontSee(route('players.show', $author->name), false)
        ->assertSee('опубликовал новость');
});
