<?php

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Livewire\EventsBoard;
use App\Livewire\EventSignup;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\User;
use App\Notifications\EventParticipantJoinedNotification;
use App\Notifications\EventParticipantLeftNotification;
use Database\Seeders\DashboardWidgetSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function d23User(string $role = 'user', bool $verified = true): User
{
    $user = User::factory()->create([
        'email_verified_at' => $verified ? now() : null,
    ]);
    $user->assignRole($role);

    return $user;
}

function d23Type(string $key): EventType
{
    return EventType::create([
        'key' => $key,
        'title' => ucfirst($key),
        'icon' => null,
        'sort_order' => 10,
        'is_active' => true,
    ]);
}

function d23Event(User $leader, EventType $type, array $overrides = []): Event
{
    $event = Event::create(array_merge([
        'user_id' => $leader->id,
        'type_id' => $type->id,
        'title' => 'Livewire event',
        'description' => 'Description for the Livewire event.',
        'starts_at' => now()->addDays(3),
        'max_participants' => null,
        'registration_close' => null,
        'status' => EventStatus::Planned,
    ], $overrides));

    EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => $leader->id,
        'status' => EventParticipantStatus::Joined,
        'joined_at' => now(),
        'left_at' => null,
    ]);

    return $event;
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(DashboardWidgetSeeder::class);
    Notification::fake();
});

it('renders the events board on the community dashboard', function () {
    $this->actingAs(d23User())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('Ближайшие события');
});

it('shows only planned events in the next fourteen days', function () {
    $leader = d23User();
    $type = d23Type('party');
    $upcoming = d23Event($leader, $type);
    $tooFar = d23Event($leader, $type, [
        'title' => 'Outside event window',
        'starts_at' => now()->addDays(15),
    ]);
    d23Event($leader, $type, [
        'title' => 'Past event',
        'starts_at' => now()->subDay(),
    ]);
    d23Event($leader, $type, [
        'title' => 'Cancelled event',
        'status' => EventStatus::Cancelled,
    ]);

    Livewire::test(EventsBoard::class)
        ->assertSee($upcoming->title)
        ->assertDontSee($tooFar->title)
        ->assertDontSee('Past event')
        ->assertDontSee('Cancelled event');
});

it('filters upcoming events by event type', function () {
    $leader = d23User();
    $partyType = d23Type('party');
    $raidType = d23Type('raid');
    $party = d23Event($leader, $partyType, ['title' => 'Party filter target']);
    d23Event($leader, $raidType, ['title' => 'Raid filter target']);

    Livewire::test(EventsBoard::class)
        ->set('type', $partyType->key)
        ->assertSee($party->title)
        ->assertDontSee('Raid filter target');
});

it('joins through Livewire and updates the participant count without a page reload', function () {
    $leader = d23User();
    $attendee = d23User();
    $event = d23Event($leader, d23Type('party'));

    Livewire::actingAs($attendee)
        ->test(EventSignup::class, ['event' => $event])
        ->assertSee('1 участников')
        ->call('join')
        ->assertSee('2 участников')
        ->assertSee('Выйти');

    expect($event->participants()->where('user_id', $attendee->id)->value('status'))
        ->toBe(EventParticipantStatus::Joined);
    Notification::assertSentTo($leader, EventParticipantJoinedNotification::class);
});

it('leaves through Livewire and updates the participant count without a page reload', function () {
    $leader = d23User();
    $attendee = d23User();
    $event = d23Event($leader, d23Type('party'));
    EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
        'status' => EventParticipantStatus::Joined,
        'joined_at' => now(),
        'left_at' => null,
    ]);

    Livewire::actingAs($attendee)
        ->test(EventSignup::class, ['event' => $event])
        ->assertSee('2 участников')
        ->call('leave')
        ->assertSee('1 участников')
        ->assertSee('Записаться');

    Notification::assertSentTo($leader, EventParticipantLeftNotification::class);
});

it('rechecks policy and denies signup by unverified users', function () {
    $event = d23Event(d23User(), d23Type('party'));

    Livewire::actingAs(d23User(verified: false))
        ->test(EventSignup::class, ['event' => $event])
        ->call('join')
        ->assertForbidden();
});

it('rechecks policy and denies signup without the event permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $event = d23Event(d23User(), d23Type('party'));

    Livewire::actingAs($user)
        ->test(EventSignup::class, ['event' => $event])
        ->call('join')
        ->assertForbidden();
});

it('prevents a full event from accepting signup in the Livewire action', function () {
    $leader = d23User();
    $event = d23Event($leader, d23Type('party'), ['max_participants' => 1]);
    $attendee = d23User();

    Livewire::actingAs($attendee)
        ->test(EventSignup::class, ['event' => $event])
        ->assertSee('Мест нет')
        ->call('join')
        ->assertHasErrors('event');

    expect($event->participants()->count())->toBe(1);
});

it('keeps leave available for a participant after cancellation', function () {
    $leader = d23User();
    $attendee = d23User();
    $event = d23Event($leader, d23Type('party'), ['status' => EventStatus::Cancelled]);
    EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
        'status' => EventParticipantStatus::Joined,
        'joined_at' => now(),
        'left_at' => null,
    ]);

    Livewire::actingAs($attendee)
        ->test(EventSignup::class, ['event' => $event])
        ->assertSee('Выйти')
        ->call('leave')
        ->assertSee('Регистрация закрыта');
});
