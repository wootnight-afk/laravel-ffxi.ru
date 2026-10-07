<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Resolve the application schedule, which triggers the `withSchedule`
 * callback declared in bootstrap/app.php.
 *
 * @return array<int, Event>
 */
function backupScheduledEvents(): array
{
    return app(Schedule::class)->events();
}

/**
 * Locate the scheduled event whose command contains the given fragment.
 */
function findBackupScheduledEvent(string $commandFragment): Event
{
    foreach (backupScheduledEvents() as $event) {
        if (is_string($event->command) && str_contains($event->command, $commandFragment)) {
            return $event;
        }
    }

    throw new RuntimeException("No scheduled event found for [{$commandFragment}].");
}

it('schedules the daily db-only backup at 04:00 without overlapping', function () {
    $event = findBackupScheduledEvent('app:backup --mode=db --triggered-by=cron');

    expect($event->expression)->toBe('0 4 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('schedules the daily backup cleanup at 04:30 without overlapping', function () {
    $event = findBackupScheduledEvent('app:backup-cleanup');

    expect($event->expression)->toBe('30 4 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
