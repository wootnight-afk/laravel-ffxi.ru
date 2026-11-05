<?php

declare(strict_types=1);

use App\Support\EventDateInput;

it('normalizes supported local date formats', function () {
    expect(EventDateInput::normalize('2026-11-07T18:00'))->toBe('2026-11-07 18:00')
        ->and(EventDateInput::normalize('2026-11-07 18:00:00'))->toBe('2026-11-07 18:00')
        ->and(EventDateInput::normalize('2026-11-07 18:00'))->toBe('2026-11-07 18:00')
        ->and(EventDateInput::normalize('not a date'))->toBeNull();
});

it('converts a valid local event time to UTC', function () {
    expect(EventDateInput::toUtc('2026-11-07 18:00', 'Europe/Moscow'))
        ->toBe(['2026-11-07 15:00:00', null]);

    expect(EventDateInput::toUtc('2026-11-07 18:00', 'UTC'))
        ->toBe(['2026-11-07 18:00:00', null]);
});

it('rejects a non-existent local time at the spring transition', function () {
    expect(EventDateInput::toUtc('2026-03-29 02:30', 'Europe/Berlin'))
        ->toBe([null, EventDateInput::ERROR_NON_EXISTENT]);

    expect(EventDateInput::toUtc('2026-03-08 02:30', 'America/New_York'))
        ->toBe([null, EventDateInput::ERROR_NON_EXISTENT]);
});

it('rejects an ambiguous local time at the autumn transition', function () {
    expect(EventDateInput::toUtc('2026-10-25 02:30', 'Europe/Berlin'))
        ->toBe([null, EventDateInput::ERROR_AMBIGUOUS]);

    expect(EventDateInput::toUtc('2026-11-01 01:30', 'America/New_York'))
        ->toBe([null, EventDateInput::ERROR_AMBIGUOUS]);
});

it('accepts a local time just after the repeated interval', function () {
    expect(EventDateInput::toUtc('2026-10-25 03:30', 'Europe/Berlin'))
        ->toBe(['2026-10-25 02:30:00', null]);
});

it('never rejects UTC input', function () {
    expect(EventDateInput::toUtc('2026-03-29 02:30', 'UTC'))
        ->toBe(['2026-03-29 02:30:00', null]);
});
