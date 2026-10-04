<?php

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Jobs\DispatchEventRemindersJob;
use App\Jobs\SendEventStartingSoonReminderJob;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Notifications\EventStartingSoonNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

function d7Event(EventStatus $status = EventStatus::Planned, ?Carbon $startsAt = null): Event
{
    return Event::create([
        'title' => 'D7 reminder event',
        'description' => 'Reminder test event.',
        'starts_at' => $startsAt ?? now()->addHour(),
        'status' => $status,
    ]);
}

function d7Participant(Event $event, User $user, EventParticipantStatus $status = EventParticipantStatus::Joined): EventParticipant
{
    return EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'status' => $status,
        'joined_at' => now(),
        'left_at' => $status === EventParticipantStatus::Left ? now() : null,
    ]);
}

function d7RunReminder(Event $event): void
{
    new SendEventStartingSoonReminderJob(
        (int) $event->getKey(),
        $event->starts_at->toIso8601String(),
    )->handle();
}

function d7ReminderCount(User $user): int
{
    return $user->notifications()
        ->where('type', EventStartingSoonNotification::class)
        ->count();
}

beforeEach(function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
});

it('sends a reminder for a planned event in the due window', function () {
    Bus::fake();
    $event = d7Event();
    $user = User::factory()->create();
    d7Participant($event, $user);

    (new DispatchEventRemindersJob)->handle();

    Bus::assertDispatched(SendEventStartingSoonReminderJob::class, function (SendEventStartingSoonReminderJob $job): bool {
        $job->handle();

        return true;
    });

    expect(d7ReminderCount($user))->toBe(1);
});

it('does not dispatch reminders for events outside the due window', function () {
    Bus::fake();
    $event = d7Event(startsAt: now()->addHours(2));
    d7Participant($event, User::factory()->create());

    (new DispatchEventRemindersJob)->handle();

    Bus::assertNotDispatched(SendEventStartingSoonReminderJob::class);
});

it('does not remind participants about cancelled events', function () {
    $event = d7Event(EventStatus::Cancelled);
    $user = User::factory()->create();
    d7Participant($event, $user);

    d7RunReminder($event);

    expect(d7ReminderCount($user))->toBe(0);
});

it('does not remind participants about completed events', function () {
    $event = d7Event(EventStatus::Completed);
    $user = User::factory()->create();
    d7Participant($event, $user);

    d7RunReminder($event);

    expect(d7ReminderCount($user))->toBe(0);
});

it('does not remind participants about soft-deleted events', function () {
    $event = d7Event();
    $user = User::factory()->create();
    d7Participant($event, $user);
    $event->delete();

    d7RunReminder($event);

    expect(d7ReminderCount($user))->toBe(0);
});

it('sends reminders only to currently joined participants', function () {
    $event = d7Event();
    $joined = User::factory()->create();
    $left = User::factory()->create();
    d7Participant($event, $joined);
    d7Participant($event, $left, EventParticipantStatus::Left);

    d7RunReminder($event);

    expect(d7ReminderCount($joined))->toBe(1)
        ->and(d7ReminderCount($left))->toBe(0);
});

it('does not notify a participant who left before the reminder job runs', function () {
    $event = d7Event();
    $user = User::factory()->create();
    $participant = d7Participant($event, $user);
    $participant->update([
        'status' => EventParticipantStatus::Left,
        'left_at' => now(),
    ]);

    d7RunReminder($event);

    expect(d7ReminderCount($user))->toBe(0);
});

it('does not create duplicate reminders when the job runs more than once', function () {
    $event = d7Event();
    $user = User::factory()->create();
    d7Participant($event, $user);

    d7RunReminder($event);
    d7RunReminder($event);

    expect(d7ReminderCount($user))->toBe(1);
});

it('sends a new reminder after the event start time changes', function () {
    $event = d7Event();
    $user = User::factory()->create();
    d7Participant($event, $user);

    d7RunReminder($event);
    $event->update(['starts_at' => now()->addHours(2)]);
    d7RunReminder($event->fresh());

    expect(d7ReminderCount($user))->toBe(2);
});

it('includes the event details and link in the database notification', function () {
    $event = d7Event();
    $user = User::factory()->create();
    d7Participant($event, $user);

    d7RunReminder($event);

    $data = $user->notifications()->firstOrFail()->data;
    expect($data)->toMatchArray([
        'event_id' => $event->id,
        'title' => $event->title,
        'starts_at' => $event->starts_at->toIso8601String(),
        'event_starts_at' => $event->starts_at->toIso8601String(),
        'url' => route('events.show', $event),
    ]);
});
