<?php

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\User;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventParticipantJoinedNotification;
use App\Notifications\EventParticipantLeftNotification;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

function d22User(string $role = 'user', bool $verified = true): User
{
    $user = User::factory()->create([
        'email_verified_at' => $verified ? now() : null,
    ]);
    $user->assignRole($role);

    return $user;
}

function d22Type(string $key = 'party', bool $active = true): EventType
{
    return EventType::create([
        'key' => $key,
        'title' => ucfirst($key),
        'icon' => null,
        'sort_order' => 10,
        'is_active' => $active,
    ]);
}

function d22Event(User $leader, array $overrides = []): Event
{
    $type = $overrides['type_id'] ?? d22Type();
    unset($overrides['type_id']);

    $event = Event::create(array_merge([
        'user_id' => $leader->id,
        'type_id' => $type instanceof EventType ? $type->id : $type,
        'title' => 'Test event',
        'description' => 'A sufficiently detailed event description.',
        'location' => 'Jeuno',
        'starts_at' => now()->addDays(3),
        'duration_minutes' => 60,
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

function d22Participation(Event $event, User $user, EventParticipantStatus $status = EventParticipantStatus::Joined): EventParticipant
{
    return EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'status' => $status,
        'joined_at' => now(),
        'left_at' => $status === EventParticipantStatus::Left ? now() : null,
    ]);
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Notification::fake();
});

it('serves the public events index and upcoming event detail', function () {
    $event = d22Event(d22User());

    $this->get(route('events.index'))->assertOk()->assertSee('Test event');
    $this->get(route('events.show', $event))->assertOk()->assertSee('A sufficiently detailed event description.');
});

it('respects guest access configuration for public event pages', function () {
    app(SettingsRepository::class)->set('guest_sections', ['events' => false]);

    $this->get(route('events.index'))->assertNotFound();
});

it('filters the event index by active type and lists only planned upcoming events', function () {
    $leader = d22User();
    $party = d22Type('party');
    $raid = d22Type('raid');
    $upcomingParty = d22Event($leader, ['type_id' => $party]);
    d22Event($leader, ['type_id' => $raid, 'title' => 'Raid-only event']);
    d22Event($leader, ['type_id' => $party, 'starts_at' => now()->subDay()]);
    d22Event($leader, ['type_id' => $party, 'status' => EventStatus::Cancelled]);

    $this->get(route('events.index', ['type' => 'party']))
        ->assertOk()
        ->assertSee($upcomingParty->title)
        ->assertDontSee('Raid-only event');
});

it('does not expose participant names to guests', function () {
    $event = d22Event(d22User());
    $attendee = d22User();
    d22Participation($event, $attendee);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('2 участников')
        ->assertDontSee($attendee->name);
});

it('shows joined participant names to authenticated viewers', function () {
    $event = d22Event(d22User());
    $attendee = d22User();
    d22Participation($event, $attendee);

    $this->actingAs(d22User())
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee($attendee->name);
});

it('renders event descriptions through the content sanitizer', function () {
    $event = d22Event(d22User(), ['description' => '<script>alert(1)</script>']);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertDontSee('<script')
        ->assertDontSee('alert(1)');
});

it('requires authentication and verified email to open the create form', function () {
    $this->get(route('events.create'))->assertRedirect(route('login'));

    $this->actingAs(d22User(verified: false))
        ->get(route('events.create'))
        ->assertRedirect(route('verification.notice'));
});

it('allows a verified event creator to open the create form', function () {
    $this->actingAs(d22User())
        ->get(route('events.create'))
        ->assertOk()
        ->assertSee('Создать событие');
});

it('creates an event with the leader automatically joined', function () {
    $leader = d22User();
    $type = d22Type();

    $response = $this->actingAs($leader)->post(route('events.store'), [
        'type_id' => $type->id,
        'title' => 'A new adventure',
        'description' => 'Meet in Jeuno for an evening run.',
        'location' => 'Jeuno',
        'starts_at' => now()->addDays(5)->setTimezone('Europe/Moscow')->format('Y-m-d\TH:i'),
        'duration_minutes' => 90,
        'max_participants' => 12,
    ]);

    $event = Event::query()->where('title', 'A new adventure')->firstOrFail();

    $response->assertRedirect(route('events.show', $event));
    expect($event->status)->toBe(EventStatus::Planned)
        ->and($event->participants()->where('user_id', $leader->id)->firstOrFail()->status)->toBe(EventParticipantStatus::Joined);
});

it('denies event creation when the user lacks the create permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->postJson(route('events.store'), [])
        ->assertForbidden();
});

it('rejects inactive event types', function () {
    $leader = d22User();
    $inactiveType = d22Type('inactive', false);

    $this->actingAs($leader)
        ->postJson(route('events.store'), [
            'type_id' => $inactiveType->id,
            'title' => 'Inactive type event',
            'description' => 'Meet for an event with an inactive type.',
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type_id');
});

it('rejects event starts in the past', function () {
    $this->actingAs(d22User())
        ->postJson(route('events.store'), [
            'type_id' => d22Type()->id,
            'title' => 'Past event',
            'description' => 'This event has an invalid past start time.',
            'starts_at' => now()->subMinute()->format('Y-m-d\TH:i'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('starts_at');
});

it('requires registration close to be before the event start', function () {
    $this->actingAs(d22User())
        ->postJson(route('events.store'), [
            'type_id' => d22Type()->id,
            'title' => 'Invalid registration window',
            'description' => 'The registration window ends too late.',
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'registration_close' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('registration_close');
});

it('enforces the participant limit between two and one hundred', function (int $limit) {
    $this->actingAs(d22User())
        ->postJson(route('events.store'), [
            'type_id' => d22Type()->id,
            'title' => 'Invalid participant limit',
            'description' => 'The participant limit is outside the allowed range.',
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'max_participants' => $limit,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_participants');
})->with([
    'too low' => [1],
    'too high' => [101],
]);

it('converts entered display time to UTC and displays it in the configured timezone', function () {
    app(SettingsRepository::class)->set('timezone_display', 'Europe/Moscow');
    $leader = d22User();
    $type = d22Type();

    $this->actingAs($leader)->post(route('events.store'), [
        'type_id' => $type->id,
        'title' => 'Timezone event',
        'description' => 'An event used to verify timezone conversion.',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect();

    $event = Event::query()->where('title', 'Timezone event')->firstOrFail();

    expect($event->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2030-01-01 09:00');
    $this->get(route('events.show', $event))->assertSee('01.01.2030 12:00');
});

it('allows the leader to edit an upcoming event', function () {
    $leader = d22User();
    $event = d22Event($leader);

    $this->actingAs($leader)
        ->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee($event->title);
});

it('denies an unrelated user access to the edit form', function () {
    $event = d22Event(d22User());

    $this->actingAs(d22User())
        ->get(route('events.edit', $event))
        ->assertForbidden();
});

it('updates an upcoming event through the event service', function () {
    $leader = d22User();
    $event = d22Event($leader);

    $this->actingAs($leader)
        ->put(route('events.update', $event), [
            'type_id' => $event->type_id,
            'title' => 'Updated title',
            'description' => $event->description,
            'location' => $event->location,
            'starts_at' => now()->addDays(4)->format('Y-m-d\TH:i'),
            'duration_minutes' => $event->duration_minutes,
            'max_participants' => $event->max_participants,
        ])
        ->assertRedirect(route('events.show', $event));

    expect($event->fresh()->title)->toBe('Updated title');
});

it('only renders description and location fields for an event that has started', function () {
    $leader = d22User();
    $event = d22Event($leader, ['starts_at' => now()->subMinute()]);

    $this->actingAs($leader)
        ->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('name="description"', false)
        ->assertSee('name="location"', false)
        ->assertDontSee('name="title"', false)
        ->assertDontSee('name="starts_at"', false);
});

it('cancels an event without removing joined participant records', function () {
    $leader = d22User();
    $attendee = d22User();
    $event = d22Event($leader);
    $attendeeParticipation = d22Participation($event, $attendee);

    $this->actingAs($leader)
        ->post(route('events.cancel', $event))
        ->assertRedirect(route('events.show', $event));

    expect($event->fresh()->status)->toBe(EventStatus::Cancelled)
        ->and($attendeeParticipation->fresh()->status)->toBe(EventParticipantStatus::Joined);
    Notification::assertSentTo($attendee, EventCancelledNotification::class);
});

it('joins and leaves an event through the participant routes', function () {
    $leader = d22User();
    $attendee = d22User();
    $event = d22Event($leader);

    $this->actingAs($attendee)->post(route('events.join', $event))->assertRedirect();
    $participation = $event->participants()->where('user_id', $attendee->id)->firstOrFail();
    Notification::assertSentTo($leader, EventParticipantJoinedNotification::class);

    $this->actingAs($attendee)->post(route('events.leave', $event))->assertRedirect();

    expect($participation->fresh()->status)->toBe(EventParticipantStatus::Left);
    Notification::assertSentTo($leader, EventParticipantLeftNotification::class);
});

it('blocks writes when the user has no section access permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->postJson(route('events.store'), [])
        ->assertForbidden();
});
