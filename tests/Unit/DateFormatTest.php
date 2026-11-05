<?php

declare(strict_types=1);

use App\Support\DateFormat;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('formats calendar dates by locale without shifting them', function () {
    $value = Carbon::parse('2026-11-04 23:30:00', 'UTC');

    expect(DateFormat::date($value, 'ru'))->toBe('04.11.2026')
        ->and(DateFormat::date($value, 'en'))->toBe('Nov 04, 2026')
        ->and(DateFormat::date(null, 'ru'))->toBe(DateFormat::PLACEHOLDER);
});

it('formats a moment in the viewer timezone', function () {
    $value = Carbon::parse('2026-11-07 18:00:00', 'UTC');

    expect(DateFormat::moment($value, 'Europe/Berlin', 'ru'))->toBe('07.11.2026 19:00')
        ->and(DateFormat::moment($value, 'Europe/Berlin', 'en'))->toBe('Nov 07, 2026 19:00')
        ->and(DateFormat::moment($value, 'Europe/Berlin', 'ru', 'time'))->toBe('19:00')
        ->and(DateFormat::moment($value, 'Europe/Berlin', 'ru', 'datetime', true))->toBe('07.11.2026 19:00 (Europe/Berlin)');
});

it('always renders system timestamps in UTC with a marker', function () {
    $value = Carbon::parse('2026-11-07 18:00:00', 'UTC');

    expect(DateFormat::utc($value, 'ru'))->toBe('07.11.2026 18:00 (UTC)')
        ->and(DateFormat::utc($value, 'en'))->toBe('Nov 07, 2026 18:00 (UTC)')
        ->and(DateFormat::utc(null, 'ru'))->toBe(DateFormat::PLACEHOLDER);
});

it('adds the local event time only when a viewer timezone is known', function () {
    $value = Carbon::parse('2026-11-07 18:00:00', 'UTC');

    expect(DateFormat::eventLocal($value, 'Europe/Berlin', 'ru'))->toBe('19:00 Europe/Berlin')
        ->and(DateFormat::eventLocal($value, 'UTC', 'ru'))->toBeNull()
        ->and(DateFormat::eventLocal($value, 'Mars/Olympus', 'ru'))->toBeNull();
});

it('shows the date when the local event time crosses midnight', function () {
    $value = Carbon::parse('2026-11-07 18:00:00', 'UTC');

    expect(DateFormat::eventLocal($value, 'Asia/Tokyo', 'ru'))->toBe('08.11.2026 03:00 Asia/Tokyo');
});

it('labels chat dividers by the viewer local date', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00', 'UTC'));

    $today = Carbon::parse('2026-11-04 09:00:00', 'UTC');
    $yesterday = Carbon::parse('2026-11-03 12:00:00', 'UTC');
    $older = Carbon::parse('2026-10-01 12:00:00', 'UTC');

    expect(DateFormat::localDayLabel($today, 'Europe/Berlin', 'ru'))->toBe('Сегодня')
        ->and(DateFormat::localDayLabel($yesterday, 'Europe/Berlin', 'ru'))->toBe('Вчера')
        ->and(DateFormat::localDayLabel($older, 'Europe/Berlin', 'ru'))->toBe('01.10.2026');
});

it('produces an ISO value for the time element', function () {
    $value = Carbon::parse('2026-11-07 18:00:00', 'UTC');

    expect(DateFormat::iso($value, 'Europe/Berlin'))->toBe('2026-11-07T19:00:00+01:00')
        ->and(DateFormat::iso($value, 'UTC'))->toBe('2026-11-07T18:00:00+00:00')
        ->and(DateFormat::iso(null))->toBeNull();
});

it('accepts strings and rejects unparsable values', function () {
    expect(DateFormat::toCarbon('2026-11-07 18:00:00')?->toDateTimeString())->toBe('2026-11-07 18:00:00')
        ->and(DateFormat::toCarbon('not a date'))->toBeNull()
        ->and(DateFormat::toCarbon(null))->toBeNull();
});
