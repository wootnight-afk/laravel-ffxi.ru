<?php

use App\Enums\ActivityType;
use App\Enums\EventStatus;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Event;
use App\Models\News;
use App\Models\User;
use App\Services\ActivityFeedGrouper;
use App\Services\ActivityLogger;
use App\Services\ActivitySubjectResolver;
use App\Services\SettingsRepository;
use Database\Seeders\DashboardWidgetSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function d3FeedUser(string $role = 'user'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function d3FeedEvent(User $leader, array $attributes = []): Event
{
    return Event::create(array_merge([
        'user_id' => $leader->id,
        'title' => 'Activity event',
        'description' => 'A description for the activity event.',
        'starts_at' => now()->addDay(),
        'status' => EventStatus::Planned,
    ], $attributes));
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(DashboardWidgetSeeder::class);
});

it('requires authentication for the full activity route', function () {
    $this->get(route('activity.index'))->assertRedirect(route('login'));
});

it('renders the authenticated activity feed with pagination', function () {
    $actor = d3FeedUser();
    $logger = app(ActivityLogger::class);

    foreach (range(1, 52) as $index) {
        $logger->log(ActivityType::Registered, $actor, $actor, ['name' => "Activity user {$index}"]);
    }

    $this->actingAs($actor)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertSee('Активность сообщества')
        ->assertSee('Activity user 52')
        ->assertDontSee('Activity user 1');

    expect(Activity::query()->recent()->paginate(50)->count())->toBe(50);
});

it('shows at most fifteen recent entries on the dashboard and links to the full feed', function () {
    $actor = d3FeedUser();
    $logger = app(ActivityLogger::class);
    $names = [
        'DashboardStrangerAlpha',
        'DashboardStrangerBravo',
        'DashboardStrangerCharlie',
        'DashboardStrangerDelta',
        'DashboardStrangerEcho',
        'DashboardStrangerFoxtrot',
        'DashboardStrangerGolf',
        'DashboardStrangerHotel',
        'DashboardStrangerIndia',
        'DashboardStrangerJuliet',
        'DashboardStrangerKilo',
        'DashboardStrangerLima',
        'DashboardStrangerMike',
        'DashboardStrangerNovember',
        'DashboardStrangerOscar',
        'DashboardStrangerPapa',
        'DashboardStrangerQuebec',
        'DashboardStrangerZulu',
    ];

    foreach ($names as $name) {
        $logger->log(ActivityType::Registered, $actor, $actor, ['name' => $name]);
    }

    $this->actingAs($actor)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('Вся активность')
        ->assertSee('DashboardStrangerZulu')
        ->assertDontSee('DashboardStrangerAlpha');
});

it('groups only adjacent entries with the same actor and activity type', function () {
    $actor = d3FeedUser();
    $other = d3FeedUser();
    $logger = app(ActivityLogger::class);

    $logger->log(ActivityType::Registered, $actor, $actor);
    $logger->log(ActivityType::Registered, $actor, $actor);
    $logger->log(ActivityType::Registered, $other, $other);
    $logger->log(ActivityType::NewsPublished, $actor, null);
    $logger->log(ActivityType::Registered, $actor, $actor);

    $activities = Activity::query()->recent()->get()->reverse()->values();
    $groups = app(ActivityFeedGrouper::class)->group($activities);

    expect(array_map(fn (array $group): int => count($group['items']), $groups))
        ->toBe([2, 1, 1, 1]);
});

it('links to an accessible published news subject', function () {
    $viewer = d3FeedUser();
    $author = d3FeedUser();
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Published feed item',
        'body' => 'Public body of the published news.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);
    $activity = app(ActivityLogger::class)->log(
        ActivityType::NewsPublished,
        $author,
        $news,
        ['title' => $news->title],
    );

    expect(app(ActivitySubjectResolver::class)->url($activity, $viewer))
        ->toBe(route('news.show', $news->slug));
});

it('does not link to an inaccessible news subject', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $author = d3FeedUser();
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Restricted feed item',
        'body' => 'Public body of the restricted news.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);
    $activity = app(ActivityLogger::class)->log(ActivityType::NewsPublished, $author, $news, ['title' => $news->title]);

    expect(app(ActivitySubjectResolver::class)->url($activity, $viewer))->toBeNull();
});

it('does not expose a closed-profile player news title in the activity feed', function () {
    $author = d3FeedUser();
    $author->forceFill(['is_profile_public' => false])->saveQuietly();
    $title = 'Hidden player news '.Str::random(10);
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_PLAYER,
        'title' => $title,
        'body' => 'Player news body for a closed profile.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);
    app(ActivityLogger::class)->log(
        ActivityType::NewsPublished,
        $author,
        $news,
        ['title' => $news->title, 'scope' => 'player'],
    );
    $viewer = d3FeedUser();

    $this->actingAs($viewer)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertDontSee($title);
});

it('does not link to a soft-deleted event subject', function () {
    $viewer = d3FeedUser();
    $author = d3FeedUser();
    $event = d3FeedEvent($author);
    $activity = app(ActivityLogger::class)->log(ActivityType::EventCreated, $author, $event, ['title' => $event->title]);
    $event->delete();
    $activity->unsetRelation('subject')->load('subject');

    expect($activity->subject)->toBeNull()
        ->and(app(ActivitySubjectResolver::class)->url($activity, $viewer))->toBeNull();
});

it('renders a deleted actor as an account-deleted placeholder without a profile link', function () {
    $viewer = d3FeedUser();
    $actor = d3FeedUser();
    $activity = app(ActivityLogger::class)->log(ActivityType::Registered, $actor, $actor, ['name' => $actor->name]);
    $actor->delete();

    $this->actingAs($viewer)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertSee('[аккаунт удалён]')
        ->assertDontSee(route('players.show', $actor->name));

    expect($activity->fresh()->actor)->toBeNull();
});

it('preserves activity when its actor is hard deleted and nulls actor_id', function () {
    $actor = d3FeedUser();
    $activity = app(ActivityLogger::class)->log(ActivityType::Registered, $actor, null, ['name' => $actor->name]);
    $actor->forceDelete();

    expect($activity->fresh()->actor_id)->toBeNull()
        ->and($activity->fresh()->exists)->toBeTrue();
});

it('records exactly one activity for an approved news comment', function () {
    $author = d3FeedUser('admin');
    $commenter = d3FeedUser();
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Comment activity acceptance',
        'body' => 'A published news item for the comment activity regression.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
        'comments_enabled' => true,
    ]);
    app(SettingsRepository::class)->set('comments_moderation', 'none');

    $response = $this->actingAs($commenter)
        ->postJson(route('comments.store', $news->slug), [
            'body' => 'Approved comment creates one activity record.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', Comment::STATUS_APPROVED);

    $commentId = $response->json('id');
    $this->assertDatabaseCount('activities', 1);
    $this->assertDatabaseHas('activities', [
        'type' => ActivityType::CommentCreated->value,
        'actor_id' => $commenter->id,
        'subject_type' => (new Comment)->getMorphClass(),
        'subject_id' => $commentId,
    ]);
});
