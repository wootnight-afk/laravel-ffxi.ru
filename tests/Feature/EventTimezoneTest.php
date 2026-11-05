<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function l3TzUser(string $role = 'user'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function l3TzType(): EventType
{
    return EventType::create([
        'key' => 'l3-'.uniqid(),
        'title' => 'L3 event type',
        'is_active' => true,
        'sort_order' => 10,
    ]);
}

function l3TzEvent(array $overrides = []): Event
{
    $leader = l3TzUser();

    return Event::create(array_merge([
        'user_id' => $leader->id,
        'title' => 'L3 event',
        'description' => 'A sufficiently detailed event description.',
        'starts_at' => '2030-01-01 18:00:00',
        'status' => EventStatus::Planned,
    ], $overrides));
}

afterEach(function () {
    Carbon::setTestNow();
});

it('stores the declared timezone and converts the local start to UTC', function () {
    $leader = l3TzUser();
    $type = l3TzType();

    $this->actingAs($leader)->post(route('events.store'), [
        'type_id' => $type->id,
        'title' => 'Declared timezone event',
        'description' => 'An event with an explicit declaration timezone.',
        'timezone' => 'Europe/Moscow',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect();

    $event = Event::query()->where('title', 'Declared timezone event')->firstOrFail();

    expect($event->timezone)->toBe('Europe/Moscow')
        ->and($event->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2030-01-01 09:00');
});

it('defaults the declared timezone to the organizer timezone', function () {
    $leader = l3TzUser();
    $leader->forceFill(['timezone' => 'Europe/Berlin'])->save();
    $type = l3TzType();

    $this->actingAs($leader)->post(route('events.store'), [
        'type_id' => $type->id,
        'title' => 'Organizer timezone event',
        'description' => 'An event without an explicit declaration timezone.',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect();

    $event = Event::query()->where('title', 'Organizer timezone event')->firstOrFail();

    expect($event->timezone)->toBe('Europe/Berlin')
        ->and($event->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2030-01-01 11:00');
});

it('rejects a non-existent local event time', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $this->actingAs(l3TzUser())
        ->postJson(route('events.store'), [
            'type_id' => l3TzType()->id,
            'title' => 'Spring DST event',
            'description' => 'An event at a non-existent local time.',
            'timezone' => 'Europe/Berlin',
            'starts_at' => '2026-03-29T02:30',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('starts_at');

    expect(Event::query()->where('title', 'Spring DST event')->exists())->toBeFalse();
});

it('rejects an ambiguous local event time', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $this->actingAs(l3TzUser())
        ->postJson(route('events.store'), [
            'type_id' => l3TzType()->id,
            'title' => 'Autumn DST event',
            'description' => 'An event at an ambiguous local time.',
            'timezone' => 'Europe/Berlin',
            'starts_at' => '2026-10-25T02:30',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('starts_at');

    expect(Event::query()->where('title', 'Autumn DST event')->exists())->toBeFalse();
});

it('accepts a local time just after the repeated interval', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $this->actingAs(l3TzUser())
        ->post(route('events.store'), [
            'type_id' => l3TzType()->id,
            'title' => 'Autumn valid event',
            'description' => 'An event after the ambiguous interval.',
            'timezone' => 'Europe/Berlin',
            'starts_at' => '2026-10-25T03:30',
        ])
        ->assertRedirect();

    $event = Event::query()->where('title', 'Autumn valid event')->firstOrFail();

    expect($event->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2026-10-25 02:30');
});

it('rejects a non-existent local time in the admin form', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    Livewire::actingAs(l3TzUser('editor'))
        ->test(CreateEvent::class)
        ->set('data.title', 'Admin spring DST event')
        ->set('data.type_id', l3TzType()->id)
        ->set('data.description', 'An admin event at a non-existent local time.')
        ->set('data.timezone', 'Europe/Berlin')
        ->set('data.starts_at', '2026-03-29 02:30:00')
        ->call('create')
        ->assertHasErrors('data.starts_at');
});

it('treats the same input identically in the admin and public forms', function () {
    Carbon::setTestNow('2029-01-01 00:00:00');

    $editor = l3TzUser('editor');
    $type = l3TzType();

    Livewire::actingAs($editor)
        ->test(CreateEvent::class)
        ->set('data.title', 'Admin timezone event')
        ->set('data.type_id', $type->id)
        ->set('data.description', 'An admin event with a declared timezone.')
        ->set('data.timezone', 'Europe/Berlin')
        ->set('data.starts_at', '2030-01-01 12:00:00')
        ->call('create')
        ->assertHasNoErrors();

    $adminEvent = Event::query()->where('title', 'Admin timezone event')->firstOrFail();

    $this->actingAs(l3TzUser())->post(route('events.store'), [
        'type_id' => $type->id,
        'title' => 'Public timezone event',
        'description' => 'A public event with the same declared timezone.',
        'timezone' => 'Europe/Berlin',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect();

    $publicEvent = Event::query()->where('title', 'Public timezone event')->firstOrFail();

    expect($adminEvent->timezone)->toBe($publicEvent->timezone)
        ->and($adminEvent->starts_at->format('Y-m-d H:i:s'))
        ->toBe($publicEvent->starts_at->format('Y-m-d H:i:s'))
        ->and($adminEvent->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2030-01-01 11:00');
});

it('shows the local date when the viewer time crosses midnight', function () {
    $event = l3TzEvent([
        'timezone' => 'UTC',
        'starts_at' => '2030-01-01 23:00:00',
    ]);

    $this->withUnencryptedCookie('tz', 'Asia/Tokyo')
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('01.01.2030 23:00 (UTC)')
        ->assertSee('02.01.2030 08:00 Asia/Tokyo');
});

it('shows only UTC for a legacy event without a declared timezone', function () {
    $event = l3TzEvent([
        'timezone' => null,
        'starts_at' => '2030-01-01 23:00:00',
    ]);

    $this->withUnencryptedCookie('tz', 'Asia/Tokyo')
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('01.01.2030 23:00 (UTC)')
        ->assertDontSee('Asia/Tokyo');
});

// ------------------------------------------------------------------
// Legacy `timezone = NULL` must survive an ordinary edit (ADR-011 §2.7,
// §8.1). Both forms preselect UTC, but that value is a placeholder, not a
// declaration made by the organizer.
// ------------------------------------------------------------------

it('keeps a legacy event without a declared timezone after a public form edit', function () {
    $leader = l3TzUser();
    $event = l3TzEvent([
        'user_id' => $leader->id,
        'timezone' => null,
        'starts_at' => now()->addDays(5),
    ]);

    $this->actingAs($leader)->put(route('events.update', $event), [
        'title' => 'Legacy event updated',
        'description' => 'Updated description of a legacy event.',
        'timezone' => 'UTC',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect(route('events.show', $event));

    $event->refresh();

    expect($event->timezone)->toBeNull()
        ->and($event->description)->toBe('Updated description of a legacy event.')
        ->and($event->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2030-01-01 12:00');
});

it('keeps a legacy event without a declared timezone after an admin form edit', function () {
    $editor = l3TzUser('editor');
    $event = l3TzEvent([
        'user_id' => $editor->id,
        'timezone' => null,
        'starts_at' => now()->addDays(5),
    ]);

    Livewire::actingAs($editor)
        ->test(EditEvent::class, ['record' => $event->getKey()])
        ->assertSet('data.timezone', 'UTC')
        ->set('data.description', 'Admin updated description of a legacy event.')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->timezone)->toBeNull()
        ->and($event->description)->toBe('Admin updated description of a legacy event.');
});

it('still shows only UTC for a legacy event after it has been edited', function () {
    $leader = l3TzUser();
    $event = l3TzEvent([
        'user_id' => $leader->id,
        'timezone' => null,
        'starts_at' => now()->addDays(5),
    ]);

    $this->actingAs($leader)->put(route('events.update', $event), [
        'description' => 'Edited legacy event description.',
        'timezone' => 'UTC',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect();

    $this->withUnencryptedCookie('tz', 'Asia/Tokyo')
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('01.01.2030 12:00 (UTC)')
        ->assertDontSee('Asia/Tokyo')
        ->assertDontSee('21:00 Asia/Tokyo');
});

it('lets the leader edit the allowed fields of a started legacy event', function () {
    $leader = l3TzUser();
    $event = l3TzEvent([
        'user_id' => $leader->id,
        'timezone' => null,
        'starts_at' => now()->subDay(),
    ]);

    $this->actingAs($leader)->put(route('events.update', $event), [
        'description' => 'Description updated after the start.',
    ])->assertRedirect(route('events.show', $event));

    $event->refresh();

    expect($event->timezone)->toBeNull()
        ->and($event->description)->toBe('Description updated after the start.');
});

it('saves a started legacy event in the admin form without a timezone error', function () {
    $editor = l3TzUser('editor');
    $event = l3TzEvent([
        'user_id' => $editor->id,
        'timezone' => null,
        'starts_at' => now()->subDay()->startOfMinute(),
    ]);

    Livewire::actingAs($editor)
        ->test(EditEvent::class, ['record' => $event->getKey()])
        ->set('data.description', 'Admin description updated after the start.')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->timezone)->toBeNull()
        ->and($event->description)->toBe('Admin description updated after the start.');
});

it('records a timezone explicitly chosen for a legacy event', function () {
    $leader = l3TzUser();
    $event = l3TzEvent([
        'user_id' => $leader->id,
        'timezone' => null,
        'starts_at' => now()->addDays(5),
    ]);

    $this->actingAs($leader)->put(route('events.update', $event), [
        'description' => 'Legacy event that now declares its timezone.',
        'timezone' => 'Europe/Moscow',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect();

    $event->refresh();

    expect($event->timezone)->toBe('Europe/Moscow')
        ->and($event->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2030-01-01 09:00');
});

it('keeps a declared timezone when the public form is saved unchanged', function () {
    $leader = l3TzUser();
    $event = l3TzEvent([
        'user_id' => $leader->id,
        'timezone' => 'Europe/Berlin',
        'starts_at' => now()->addDays(5),
    ]);

    $this->actingAs($leader)->put(route('events.update', $event), [
        'description' => 'Description updated without touching the timezone.',
        'timezone' => 'Europe/Berlin',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect();

    $event->refresh();

    expect($event->timezone)->toBe('Europe/Berlin')
        ->and($event->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2030-01-01 11:00');
});

it('keeps a declared timezone when the admin form is saved unchanged', function () {
    $editor = l3TzUser('editor');
    $event = l3TzEvent([
        'user_id' => $editor->id,
        'timezone' => 'Europe/Berlin',
        'starts_at' => now()->addDays(5)->startOfMinute(),
    ]);

    Livewire::actingAs($editor)
        ->test(EditEvent::class, ['record' => $event->getKey()])
        ->assertSet('data.timezone', 'Europe/Berlin')
        ->set('data.description', 'Admin description without a timezone change.')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->timezone)->toBe('Europe/Berlin')
        ->and($event->description)->toBe('Admin description without a timezone change.');
});

it('records a timezone explicitly chosen for a legacy event in the admin form', function () {
    $editor = l3TzUser('editor');
    $event = l3TzEvent([
        'user_id' => $editor->id,
        'timezone' => null,
        'starts_at' => now()->addDays(5)->startOfMinute(),
    ]);

    Livewire::actingAs($editor)
        ->test(EditEvent::class, ['record' => $event->getKey()])
        ->set('data.timezone', 'Europe/Moscow')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->timezone)->toBe('Europe/Moscow');
});

it('stores the timezone explicitly chosen for a new event', function () {
    $leader = l3TzUser();
    $leader->forceFill(['timezone' => 'Europe/Berlin'])->save();
    $type = l3TzType();

    $this->actingAs($leader)->post(route('events.store'), [
        'type_id' => $type->id,
        'title' => 'Explicitly zoned event',
        'description' => 'An event created with an explicit timezone.',
        'timezone' => 'Asia/Tokyo',
        'starts_at' => '2030-01-01T12:00',
    ])->assertRedirect();

    $event = Event::query()->where('title', 'Explicitly zoned event')->firstOrFail();

    expect($event->timezone)->toBe('Asia/Tokyo')
        ->and($event->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2030-01-01 03:00');
});
