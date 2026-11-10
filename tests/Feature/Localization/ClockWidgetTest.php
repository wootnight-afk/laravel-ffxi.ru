<?php

declare(strict_types=1);

use App\Filament\Widgets\ClockWidget;
use App\Models\User;
use App\Services\TimezoneResolver;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Livewire;

function makeClockUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
    ], $overrides));
    $user->assignRole('admin');

    return $user;
}

function clockTestRequest(array $cookies = []): Request
{
    $request = Request::create('/');

    foreach ($cookies as $name => $value) {
        $request->cookies->set($name, $value);
    }

    return $request;
}

beforeEach(function () {
    // Fixed UTC instant: 12:00 UTC is 21:00 in Asia/Tokyo and 15:00 in Moscow.
    $this->travelTo(now()->setTimezone('UTC')->setTime(12, 0, 0));

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ------------------------------------------------------------------
// Clock faces
// ------------------------------------------------------------------

it('always renders the UTC clock at UTC offset', function () {
    $admin = makeClockUser(['timezone' => 'Asia/Tokyo']);

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee('12:00:00')
        ->assertSee(__('filament.dashboard.clock.utc'))
        ->assertSee('UTC+00:00');
});

it('renders 12-hour format when the clock_format cookie is set to 12', function () {
    $admin = makeClockUser();

    Livewire::withCookie(ClockWidget::FORMAT_COOKIE, '12')
        ->actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee('12:00:00 PM');
});

it('renders 12-hour format for non-UTC zones with correct AM/PM', function () {
    // 12:00 UTC = 15:00 Moscow = 03:00 PM; 21:00 Tokyo = 09:00 PM.
    $admin = makeClockUser(['secondary_timezone' => 'Europe/Moscow']);

    Livewire::withCookie(ClockWidget::FORMAT_COOKIE, '12')
        ->actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee('03:00:00 PM')
        ->assertSee('12:00:00 PM');
});

it('defaults to 24-hour format for an invalid clock_format cookie value', function () {
    $admin = makeClockUser();

    Livewire::withCookie(ClockWidget::FORMAT_COOKIE, 'invalid')
        ->actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee('12:00:00')
        ->assertDontSee('PM');
});

it('shows the format toggle in the section header', function () {
    $admin = makeClockUser();

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee(__('filament.dashboard.clock.format_label'))
        ->assertSee(__('filament.dashboard.clock.format_24'))
        ->assertSee(__('filament.dashboard.clock.format_12'));
});

it('marks the 24-hour button as pressed by default', function () {
    $admin = makeClockUser();

    // The faces subscribe to the toggle through the shared clock-format event.
    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee('x-on:clock-format.window', false)
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('aria-pressed="false"', false);
});

it('marks the 12-hour button as pressed when the cookie is set', function () {
    $admin = makeClockUser();

    // Exactly one button carries the pressed state, and the initial Alpine
    // format mirrors the cookie so the first render already uses 12-hour time.
    $html = Livewire::withCookie(ClockWidget::FORMAT_COOKIE, '12')
        ->actingAs($admin)
        ->test(ClockWidget::class)
        ->html();

    expect(substr_count($html, 'aria-pressed="true"'))->toBe(1)
        ->and(substr_count($html, 'aria-pressed="false"'))->toBe(1)
        ->and($html)->toContain("format: '12'");
});

it('renders the toggle inside a well balanced section header', function () {
    $admin = makeClockUser();

    $html = Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->html();

    // The markup is wrapped so the fragment is parsed as a document body;
    // a mismatched tag anywhere in the widget shows up as a libxml error.
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument;
    $document->loadHTML('<div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    $errors = array_filter(libxml_get_errors(), fn (LibXMLError $e) => $e->level === LIBXML_ERR_FATAL);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($document);

    expect($errors)->toBeEmpty()
        ->and($xpath->query('.//div[@class="fi-section-header-after-ctn"]//div[@role="group"]', $document->documentElement)->length)
        ->toBe(1);
});

it('uses the tz cookie for the device clock, not users.timezone', function () {
    $admin = makeClockUser(['timezone' => 'Asia/Tokyo']);

    Livewire::withCookie(TimezoneResolver::COOKIE, 'Europe/Moscow')
        ->actingAs($admin)
        ->test(ClockWidget::class)
        ->assertDontSee('21:00:00')
        ->assertSee('15:00:00');
});

it('falls back to UTC on the device clock without a cookie', function () {
    $admin = makeClockUser(['timezone' => 'Asia/Tokyo']);

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee(__('filament.dashboard.clock.device'));
});

it('shows the empty state when no region is selected', function () {
    $admin = makeClockUser();

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee('--:--:--')
        ->assertSee(__('filament.dashboard.clock.region_none'));
});

it('shows the region clock when a valid region is selected', function () {
    $admin = makeClockUser(['secondary_timezone' => 'Europe/Moscow']);

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee('15:00:00')
        ->assertSee(__('filament.dashboard.clock.region'));
});

it('treats an invalid stored region as not selected', function () {
    $admin = makeClockUser(['secondary_timezone' => 'Mars/Olympus']);

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->assertSee(__('filament.dashboard.clock.region_none'))
        ->assertDontSee('Mars/Olympus');
});

// ------------------------------------------------------------------
// Form persistence
// ------------------------------------------------------------------

it('saves the region selection via the widget form', function () {
    $admin = makeClockUser();

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->set('data.secondary_timezone', 'Europe/Moscow')
        ->assertHasNoErrors();

    expect($admin->refresh()->secondary_timezone)->toBe('Europe/Moscow');
});

it('rejects an invalid region via the widget form', function () {
    $admin = makeClockUser();

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->set('data.secondary_timezone', 'UTC+3')
        ->assertHasErrors('data.secondary_timezone');

    expect($admin->refresh()->secondary_timezone)->toBeNull();
});

it('clears the region via the widget clearRegion action', function () {
    $admin = makeClockUser(['secondary_timezone' => 'Asia/Tokyo']);

    Livewire::actingAs($admin)
        ->test(ClockWidget::class)
        ->call('clearRegion');

    expect($admin->refresh()->secondary_timezone)->toBeNull();
});

// ------------------------------------------------------------------
// Dashboard rendering
// ------------------------------------------------------------------

it('renders all three clocks on the dashboard', function () {
    $admin = makeClockUser(['secondary_timezone' => 'Europe/Moscow']);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('12:00:00')
        ->assertSee('15:00:00')
        ->assertSee(__('filament.dashboard.clock.heading'))
        ->assertSee(__('filament.dashboard.clock.utc'))
        ->assertSee(__('filament.dashboard.clock.device'))
        ->assertSee(__('filament.dashboard.clock.region'));
});

// ------------------------------------------------------------------
// TimezoneResolver::browserTimezone
// ------------------------------------------------------------------

it('browserTimezone returns the cookie value', function () {
    $this->app->instance('request', clockTestRequest([TimezoneResolver::COOKIE => 'Asia/Tokyo']));

    expect(app(TimezoneResolver::class)->browserTimezone())->toBe('Asia/Tokyo');
});

it('browserTimezone returns null when no valid cookie', function () {
    $this->app->instance('request', clockTestRequest([TimezoneResolver::COOKIE => 'Mars/Olympus']));

    expect(app(TimezoneResolver::class)->browserTimezone())->toBeNull();
});
