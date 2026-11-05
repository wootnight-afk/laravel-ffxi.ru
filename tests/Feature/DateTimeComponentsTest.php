<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()->setLocale('ru');
});

it('renders a guest moment in UTC with a timezone marker by default', function () {
    $html = $this->blade('<x-datetime :value="$value" />', [
        'value' => Carbon::parse('2026-11-07 18:00:00', 'UTC'),
    ]);

    $html->assertSee('07.11.2026 18:00 (UTC)');
    $html->assertSee('datetime="2026-11-07T18:00:00+00:00"', false);
});

it('renders a guest moment in the browser timezone from the cookie', function () {
    $this->app['request']->cookies->set('tz', 'Europe/Berlin');

    $html = $this->blade('<x-datetime :value="$value" />', [
        'value' => Carbon::parse('2026-11-07 18:00:00', 'UTC'),
    ]);

    $html->assertSee('07.11.2026 19:00 (Europe/Berlin)');
    $html->assertSee('datetime="2026-11-07T19:00:00+01:00"', false);
});

it('renders an authenticated moment without a timezone marker', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Berlin']);
    $this->actingAs($user);

    $html = $this->blade('<x-datetime :value="$value" />', [
        'value' => Carbon::parse('2026-11-07 18:00:00', 'UTC'),
    ]);

    $html->assertSee('07.11.2026 19:00');
    $html->assertDontSee('Europe/Berlin');
});

it('does not shift a calendar date when a viewer timezone is known', function () {
    $this->app['request']->cookies->set('tz', 'Europe/Berlin');

    $html = $this->blade('<x-naive-date :value="$value" />', [
        'value' => Carbon::parse('2026-11-04 23:30:00', 'UTC'),
    ]);

    $html->assertSee('04.11.2026');
    $html->assertSee('datetime="2026-11-04"', false);
});

it('renders calendar dates in the English format', function () {
    app()->setLocale('en');

    $html = $this->blade('<x-naive-date :value="$value" />', [
        'value' => Carbon::parse('2026-11-04 23:30:00', 'UTC'),
    ]);

    $html->assertSee('Nov 04, 2026');
});

it('always renders the UTC component in UTC', function () {
    $this->app['request']->cookies->set('tz', 'Europe/Berlin');

    $html = $this->blade('<x-utc-datetime :value="$value" />', [
        'value' => Carbon::parse('2026-11-07 18:00:00', 'UTC'),
    ]);

    $html->assertSee('07.11.2026 18:00 (UTC)');
    $html->assertDontSee('19:00');
});

it('renders a placeholder for null values', function () {
    $this->blade('<x-datetime :value="null" />')->assertSee('—');
    $this->blade('<x-naive-date :value="null" />')->assertSee('—');
    $this->blade('<x-utc-datetime :value="null" />')->assertSee('—');
});
