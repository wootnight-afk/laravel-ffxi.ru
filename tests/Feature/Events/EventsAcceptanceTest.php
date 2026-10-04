<?php

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Livewire\EventSignup;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function stage7AcceptanceEventUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function stage7AcceptanceEvent(User $leader, array $attributes = []): Event
{
    $event = Event::create(array_merge([
        'user_id' => $leader->id,
        'title' => 'Stage 7 acceptance event',
        'description' => 'An event for Stage 7 acceptance coverage.',
        'starts_at' => now()->addDays(2),
        'max_participants' => null,
        'registration_close' => null,
        'status' => EventStatus::Planned,
    ], $attributes));

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
    Notification::fake();
});

it('rejects joining after registration closes and renders the closed status', function () {
    $leader = stage7AcceptanceEventUser();
    $attendee = stage7AcceptanceEventUser();
    $event = stage7AcceptanceEvent($leader, [
        'registration_close' => now()->subMinute(),
    ]);

    expect(fn () => app(EventService::class)->join($attendee, $event))
        ->toThrow(ValidationException::class);

    $this->actingAs($attendee)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Регистрация закрыта');
});

it('rejects joining a full event and renders the unavailable state', function () {
    $leader = stage7AcceptanceEventUser();
    $attendee = stage7AcceptanceEventUser();
    $event = stage7AcceptanceEvent($leader, ['max_participants' => 1]);

    expect(fn () => app(EventService::class)->join($attendee, $event))
        ->toThrow(ValidationException::class);

    Livewire::actingAs($attendee)
        ->test(EventSignup::class, ['event' => $event])
        ->assertSee('Мест нет')
        ->assertSeeHtml('disabled');
});

it('makes a full-event place available after the current participant leaves', function () {
    $leader = stage7AcceptanceEventUser();
    $attendee = stage7AcceptanceEventUser();
    $event = stage7AcceptanceEvent($leader, ['max_participants' => 1]);
    $service = app(EventService::class);

    expect(fn () => $service->join($attendee, $event))
        ->toThrow(ValidationException::class);

    $service->leave($leader, $event);
    $participant = $service->join($attendee, $event);

    expect($participant->status)->toBe(EventParticipantStatus::Joined)
        ->and($event->participants()->where('status', EventParticipantStatus::Joined)->count())->toBe(1);
});
