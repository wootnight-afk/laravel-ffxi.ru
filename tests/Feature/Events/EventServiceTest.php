<?php

declare(strict_types=1);

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventParticipantJoinedNotification;
use App\Notifications\EventParticipantLeftNotification;
use App\Notifications\EventUpdatedNotification;
use App\Services\EventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

function d21ServiceUser(string $role = 'user'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function d21ServiceEvent(User $leader, array $overrides = []): Event
{
    $event = Event::create(array_merge([
        'user_id' => $leader->id,
        'title' => 'Service test event',
        'description' => 'A sufficiently long event description.',
        'starts_at' => now()->addDay(),
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

function d21AddParticipant(Event $event, User $user, EventParticipantStatus $status = EventParticipantStatus::Joined): EventParticipant
{
    return $event->participants()->create([
        'user_id' => $user->id,
        'status' => $status,
        'joined_at' => now(),
        'left_at' => $status === EventParticipantStatus::Left ? now() : null,
    ]);
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Carbon::setTestNow();
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates a planned event and automatically joins the leader', function () {
    $leader = d21ServiceUser();
    $event = app(EventService::class)->create($leader, [
        'title' => 'Created event',
        'description' => 'Description for the created event.',
        'starts_at' => now()->addDay(),
    ]);

    $participant = $event->participants()->sole();

    expect($event->status)->toBe(EventStatus::Planned)
        ->and($participant->user_id)->toBe($leader->id)
        ->and($participant->status)->toBe(EventParticipantStatus::Joined)
        ->and($participant->joined_at)->not->toBeNull()
        ->and($participant->left_at)->toBeNull();
});

it('updates a future event and does not notify when it has no other participants', function () {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader);

    $updated = app(EventService::class)->update($leader, $event, ['title' => 'New event title']);

    expect($updated->title)->toBe('New event title');
    Notification::assertNothingSent();
});

it('notifies joined participants when a significant field changes before the event', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader);
    d21AddParticipant($event, $attendee);

    app(EventService::class)->update($leader, $event, ['title' => 'Changed title']);

    Notification::assertSentTo(
        $attendee,
        EventUpdatedNotification::class,
        fn (EventUpdatedNotification $notification, array $channels): bool => $notification->changedFields === ['title']
            && $channels === ['database'],
    );
    Notification::assertNotSentTo($leader, EventUpdatedNotification::class);
});

it('does not notify for description or location changes', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader);
    d21AddParticipant($event, $attendee);

    app(EventService::class)->update($leader, $event, [
        'description' => 'A revised event description.',
        'location' => 'New location',
    ]);

    Notification::assertNothingSent();
});

it('allows description and location changes after an event starts', function () {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader, ['starts_at' => now()->subMinute()]);

    $updated = app(EventService::class)->update($leader, $event, [
        'description' => 'Updated after the event started.',
        'location' => 'Updated location',
    ]);

    expect($updated->description)->toBe('Updated after the event started.')
        ->and($updated->location)->toBe('Updated location');
});

it('rejects significant field changes after an event starts', function (string $field, mixed $value) {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader, ['starts_at' => now()->subMinute()]);

    expect(fn () => app(EventService::class)->update($leader, $event, [$field => $value]))
        ->toThrow(ValidationException::class);
})->with([
    'title' => ['title', 'Different title'],
    'starts_at' => ['starts_at', now()->addDays(2)],
    'duration_minutes' => ['duration_minutes', 90],
    'max_participants' => ['max_participants', 20],
    'registration_close' => ['registration_close', now()->addMinutes(10)],
    'type_id' => ['type_id', 1],
]);

it('rejects updates to cancelled events in the service', function () {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader, ['status' => EventStatus::Cancelled]);

    expect(fn () => app(EventService::class)->update($leader, $event, ['description' => 'Changed']))
        ->toThrow(ValidationException::class);
});

it('cancels a planned event and notifies joined users except the initiator', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader);
    d21AddParticipant($event, $attendee);

    $cancelled = app(EventService::class)->cancel($leader, $event, 'Schedule change');

    expect($cancelled->status)->toBe(EventStatus::Cancelled)
        ->and($event->participants()->where('status', EventParticipantStatus::Joined)->count())->toBe(2);
    Notification::assertSentTo($attendee, EventCancelledNotification::class);
    Notification::assertNotSentTo($leader, EventCancelledNotification::class);
});

it('rejects cancelling an event that is not planned', function (EventStatus $status) {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader, ['status' => $status]);

    expect(fn () => app(EventService::class)->cancel($leader, $event))
        ->toThrow(ValidationException::class);
})->with([
    'cancelled' => [EventStatus::Cancelled],
    'completed' => [EventStatus::Completed],
]);

it('creates a participant record on first join and notifies the leader', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader);

    $participant = app(EventService::class)->join($attendee, $event);

    expect($participant->status)->toBe(EventParticipantStatus::Joined)
        ->and($event->participants()->count())->toBe(2);
    Notification::assertSentTo($leader, EventParticipantJoinedNotification::class);
});

it('treats repeat join as idempotent and does not send another notification', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader);
    $service = app(EventService::class);

    $first = $service->join($attendee, $event);
    $second = $service->join($attendee, $event);

    expect($second->id)->toBe($first->id)
        ->and($event->participants()->count())->toBe(2);
    Notification::assertSentToTimes($leader, EventParticipantJoinedNotification::class, 1);
});

it('rejects joining cancelled events and events whose registration has closed', function (EventStatus $status, bool $pastStart, bool $pastClose) {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader, [
        'status' => $status,
        'starts_at' => $pastStart ? now()->subMinute() : now()->addDay(),
        'registration_close' => $pastClose ? now()->subMinute() : null,
    ]);

    expect(fn () => app(EventService::class)->join(d21ServiceUser(), $event))
        ->toThrow(ValidationException::class);
})->with([
    'cancelled' => [EventStatus::Cancelled, false, false],
    'started' => [EventStatus::Planned, true, false],
    'registration closed' => [EventStatus::Planned, false, true],
]);

it('rejects joining when the last available place is already taken', function () {
    // Concurrency protection via lockForUpdate() is not tested here:
    // the current test harness cannot reliably simulate parallel
    // transactions. The lock is exercised in production; integration-
    // level race testing is out of scope for D2.1.
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader, ['max_participants' => 1]);

    expect(fn () => app(EventService::class)->join(d21ServiceUser(), $event))
        ->toThrow(ValidationException::class);
});

it('allows joins without a participant limit', function () {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader, ['max_participants' => null]);

    $participant = app(EventService::class)->join(d21ServiceUser(), $event);

    expect($participant->status)->toBe(EventParticipantStatus::Joined);
});

it('does not notify the leader when the leader joins their own event', function () {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader);
    $leaderParticipation = $event->participants()->sole();
    $leaderParticipation->update([
        'status' => EventParticipantStatus::Left,
        'left_at' => now(),
    ]);

    $joined = app(EventService::class)->join($leader, $event);

    expect($joined->id)->toBe($leaderParticipation->id)
        ->and($joined->status)->toBe(EventParticipantStatus::Joined);
    Notification::assertNothingSent();
});

it('rejoins by updating the existing participation row and resetting its dates', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader);
    $participant = d21AddParticipant($event, $attendee, EventParticipantStatus::Left);
    $previousJoinedAt = $participant->joined_at;
    $previousLeftAt = $participant->left_at;
    Carbon::setTestNow($previousJoinedAt->copy()->addMinute());

    $rejoined = app(EventService::class)->join($attendee, $event);

    expect($rejoined->id)->toBe($participant->id)
        ->and($rejoined->status)->toBe(EventParticipantStatus::Joined)
        ->and($rejoined->joined_at->greaterThan($previousJoinedAt))->toBeTrue()
        ->and($rejoined->left_at)->toBeNull()
        ->and($event->participants()->count())->toBe(2);
    Notification::assertSentTo($leader, EventParticipantJoinedNotification::class);
});

it('marks an existing participation left and notifies the leader', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader);
    $participant = d21AddParticipant($event, $attendee);

    app(EventService::class)->leave($attendee, $event);

    expect($participant->fresh()->status)->toBe(EventParticipantStatus::Left)
        ->and($participant->fresh()->left_at)->not->toBeNull();
    Notification::assertSentTo($leader, EventParticipantLeftNotification::class);
});

it('does nothing when leaving without a participation row', function () {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader);

    app(EventService::class)->leave(d21ServiceUser(), $event);

    Notification::assertNothingSent();
});

it('does nothing when leaving an already-left participation', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader);
    $participant = d21AddParticipant($event, $attendee, EventParticipantStatus::Left);
    $leftAt = $participant->left_at;

    app(EventService::class)->leave($attendee, $event);

    expect($participant->fresh()->left_at->equalTo($leftAt))->toBeTrue();
    Notification::assertNothingSent();
});

it('allows leaving after the event starts', function () {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader, ['starts_at' => now()->subMinute()]);
    $participant = d21AddParticipant($event, $attendee);

    app(EventService::class)->leave($attendee, $event);

    expect($participant->fresh()->status)->toBe(EventParticipantStatus::Left);
});

it('allows leaving cancelled and completed events', function (EventStatus $status) {
    $leader = d21ServiceUser();
    $attendee = d21ServiceUser();
    $event = d21ServiceEvent($leader, [
        'status' => $status,
        'starts_at' => now()->subMinute(),
    ]);
    $participant = d21AddParticipant($event, $attendee);

    app(EventService::class)->leave($attendee, $event);

    expect($participant->fresh()->status)->toBe(EventParticipantStatus::Left);
})->with([
    'cancelled' => [EventStatus::Cancelled],
    'completed' => [EventStatus::Completed],
]);

it('does not notify the leader when the leader leaves their own event', function () {
    $leader = d21ServiceUser();
    $event = d21ServiceEvent($leader);

    app(EventService::class)->leave($leader, $event);

    Notification::assertNothingSent();
});

it('uses only the database notification channel', function () {
    $event = d21ServiceEvent(d21ServiceUser());
    $participant = d21ServiceUser();

    $notifications = [
        new EventParticipantJoinedNotification($event, $participant),
        new EventParticipantLeftNotification($event, $participant),
        new EventCancelledNotification($event, $participant),
        new EventUpdatedNotification($event, $participant, ['title']),
    ];

    foreach ($notifications as $notification) {
        expect($notification->via($participant))->toBe(['database'])
            ->and(method_exists($notification, 'toMail'))->toBeFalse()
            ->and($notification instanceof ShouldQueue)->toBeFalse()
            ->and($notification->toArray($participant)['url'])->toBe(
                Route::has('events.show')
                    ? route('events.show', $event)
                    : url("/events/{$event->id}"),
            );
    }
});
