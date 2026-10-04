<?php

use App\Enums\ActivityType;
use App\Livewire\ActivityBell;
use App\Models\Activity;
use App\Models\ChatMessage;
use App\Models\News;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function d5BellUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function d5Activity(User $actor, string $name, Carbon $createdAt, ?string $subjectType = null, ?int $subjectId = null): Activity
{
    return Activity::query()->create([
        'actor_id' => $actor->id,
        'type' => ActivityType::Registered,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'data' => ['name' => $name],
        'created_at' => $createdAt,
    ]);
}

function d5Notification(User $user, array $data = [], ?Carbon $readAt = null): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\PasswordChangedNotification',
        'data' => $data,
        'read_at' => $readAt,
    ]);
}

beforeEach(function () {
    Cache::flush();
    app()->setLocale('ru');
    Carbon::setLocale('ru');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('does not render the bell for guests and renders it for authenticated users', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('activity-bell__trigger');

    $user = d5BellUser();
    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('activity-bell__trigger');
});

it('counts and lists only community activity after last_activity_seen_at', function () {
    $viewer = d5BellUser();
    $actor = d5BellUser();
    $seenAt = now()->subHour();
    $viewer->forceFill(['last_activity_seen_at' => $seenAt])->saveQuietly();
    d5Activity($actor, 'Seen activity sentinel', $seenAt->copy()->subMinute());
    d5Activity($actor, 'Unread activity sentinel', $seenAt->copy()->addMinute());

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->assertSee('1')
        ->assertSee('Unread activity sentinel')
        ->assertDontSee('Seen activity sentinel');
});

it('treats all activity as unread before the user has marked any as read', function () {
    $viewer = d5BellUser();
    d5Activity(d5BellUser(), 'First community notice', now()->subDays(2));
    d5Activity(d5BellUser(), 'Second community notice', now()->subMinute());

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->assertSee('First community notice')
        ->assertSee('Second community notice')
        ->assertSee('Отметить прочитанными');
});

it('counts unread personal database notifications only', function () {
    $viewer = d5BellUser();
    d5Notification($viewer, ['title' => 'Unread personal notice']);
    d5Notification($viewer, ['title' => 'Read personal notice'], now()->subMinute());

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->call('selectTab', 'personal')
        ->assertSee('Личные')
        ->assertSee('1')
        ->assertSee('Unread personal notice')
        ->assertSee('Read personal notice');
});

it('caps the combined unread badge at 99 plus', function () {
    $viewer = d5BellUser();
    foreach (range(1, 100) as $number) {
        d5Notification($viewer, ['title' => "Personal notice {$number}"]);
    }

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->assertSee('99+');
});

it('marks an owned personal notification read and redirects only to its stored internal target', function () {
    $viewer = d5BellUser();
    $notification = d5Notification($viewer, ['url' => route('players.directory')]);

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->call('openNotification', $notification->id)
        ->assertRedirect(route('players.directory'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('does not let a user open or mark another user notification read', function () {
    $viewer = d5BellUser();
    $other = d5BellUser();
    $notification = d5Notification($other, ['url' => route('players.directory')]);

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->call('openNotification', $notification->id)
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks a notification read without redirecting to an external URL', function () {
    $viewer = d5BellUser();
    $notification = d5Notification($viewer, ['url' => 'https://attacker.invalid/phish']);

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->call('openNotification', $notification->id)
        ->assertNoRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all currently unread community activity as read', function () {
    $viewer = d5BellUser();
    d5Activity(d5BellUser(), 'Activity before mark read', now()->subMinute());

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->call('markCommunityRead')
        ->assertDontSee('Activity before mark read');

    expect($viewer->fresh()->last_activity_seen_at)->not->toBeNull();
});

it('switches between the two notification tabs and rejects unknown tabs', function () {
    $viewer = d5BellUser();

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->assertSet('activeTab', 'community')
        ->call('selectTab', 'personal')
        ->assertSet('activeTab', 'personal')
        ->call('selectTab', 'invalid')
        ->assertNotFound();
});

it('renders relative times and omits links to unresolved community subjects', function () {
    $viewer = d5BellUser();
    $actor = d5BellUser();
    $message = ChatMessage::query()->create([
        'user_id' => $actor->id,
        'body' => 'Bell linked subject',
    ]);
    $activity = d5Activity($actor, 'Unresolvable chat subject', now()->subMinutes(5), $message->getMorphClass(), $message->id);

    Livewire::actingAs($viewer)
        ->test(ActivityBell::class)
        ->assertSee('5 минут назад')
        ->assertDontSee('Bell linked subject')
        ->assertDontSee(route('players.show', $actor->name));
});

it('does not leak unavailable player news title in bell', function () {
    $author = d5BellUser();
    $author->forceFill(['is_profile_public' => false])->saveQuietly();
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_PLAYER,
        'title' => 'Secret Title '.Str::random(10),
        'body' => 'Player news body for a closed profile.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);
    Activity::query()->create([
        'actor_id' => $author->id,
        'type' => ActivityType::NewsPublished,
        'subject_type' => News::class,
        'subject_id' => $news->id,
        'data' => ['title' => $news->title, 'scope' => 'player'],
        'created_at' => now(),
    ]);
    $viewer = d5BellUser();

    $this->actingAs($viewer)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertDontSee($news->title);
});
