<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Services\TimezoneOptions;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('exposes the supported locales', function () {
    expect(Locale::values())->toBe(['ru', 'en'])
        ->and(Locale::options())->toBe([
            'ru' => 'Русский',
            'en' => 'English',
        ])
        ->and(Locale::Ru->label())->toBe('Русский')
        ->and(Locale::En->label())->toBe('English');
});

it('builds a deterministic IANA list with UTC first', function () {
    $identifiers = TimezoneOptions::identifiers();

    expect($identifiers[0])->toBe('UTC')
        ->and($identifiers)->toContain('Europe/Moscow')
        ->and($identifiers)->toContain('Asia/Tokyo')
        ->and($identifiers)->toContain('America/New_York')
        ->and($identifiers)->toBe(array_values(array_unique($identifiers)))
        ->and(TimezoneOptions::identifiers())->toBe($identifiers);
});

it('groups timezones by region with UTC first', function () {
    $groups = TimezoneOptions::grouped();

    expect(array_key_first($groups))->toBe('UTC')
        ->and(array_keys($groups))->toBe([
            'UTC',
            'Africa',
            'America',
            'Antarctica',
            'Arctic',
            'Asia',
            'Atlantic',
            'Australia',
            'Europe',
            'Indian',
            'Pacific',
        ])
        ->and($groups['UTC'])->toHaveKey('UTC')
        ->and($groups['Europe'])->toHaveKey('Europe/Moscow')
        ->and($groups['Asia'])->toHaveKey('Asia/Tokyo');
});

it('renders the current UTC offset in labels', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00', 'UTC'));

    expect(TimezoneOptions::offset('Europe/Berlin'))->toBe('+02:00')
        ->and(TimezoneOptions::offset('Europe/Moscow'))->toBe('+03:00')
        ->and(TimezoneOptions::offset('UTC'))->toBe('+00:00')
        ->and(TimezoneOptions::label('Europe/Berlin'))->toBe('Europe/Berlin (UTC +02:00)');

    Carbon::setTestNow(Carbon::parse('2026-12-01 12:00:00', 'UTC'));

    expect(TimezoneOptions::offset('Europe/Berlin'))->toBe('+01:00');
});

it('validates IANA identifiers and rejects fixed offsets', function () {
    expect(TimezoneOptions::isValid('UTC'))->toBeTrue()
        ->and(TimezoneOptions::isValid('Europe/Moscow'))->toBeTrue()
        ->and(TimezoneOptions::isValid('Asia/Tokyo'))->toBeTrue()
        ->and(TimezoneOptions::isValid(null))->toBeFalse()
        ->and(TimezoneOptions::isValid(''))->toBeFalse()
        ->and(TimezoneOptions::isValid('UTC+3'))->toBeFalse()
        ->and(TimezoneOptions::isValid('+03:00'))->toBeFalse()
        ->and(TimezoneOptions::isValid('Mars/Olympus'))->toBeFalse();
});
