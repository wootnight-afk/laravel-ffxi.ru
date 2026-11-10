<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\LocalePreference;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function makePreferencesUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
    ], $overrides));
    $user->assignRole('user');

    return $user;
}

function makePreferencesEvent(array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'Timezone test event',
        'description' => 'Event used by the L1 preference tests.',
        'starts_at' => now()->addDay(),
        'status' => EventStatus::Planned,
    ], $overrides));
}

function failingQueryException(): QueryException
{
    return new QueryException(
        connectionName: 'mysql',
        sql: 'update users set locale = ?',
        bindings: ['en'],
        previous: new RuntimeException('database is unavailable'),
    );
}

// ------------------------------------------------------------------
// Schema
// ------------------------------------------------------------------

it('creates nullable locale and timezone columns without defaults', function () {
    $columns = [
        ['users', 'locale', 'varchar(16)'],
        ['users', 'timezone', 'varchar(64)'],
        ['events', 'timezone', 'varchar(64)'],
    ];

    foreach ($columns as [$table, $column, $type]) {
        $definition = collect(Schema::getColumns($table))->firstWhere('name', $column);

        expect($definition)->not->toBeNull()
            ->and($definition['nullable'])->toBeTrue()
            ->and($definition['default'])->toBeNull()
            ->and($definition['type'])->toBe($type);
    }
});

it('does not backfill or default preferences for new users', function () {
    $user = makePreferencesUser();

    expect($user->locale)->toBeNull()
        ->and($user->timezone)->toBeNull();
});

it('does not backfill or default the timezone for new events', function () {
    $event = makePreferencesEvent();

    expect($event->timezone)->toBeNull();
});

it('stores an event timezone when it is explicitly provided', function () {
    $event = makePreferencesEvent(['timezone' => 'Europe/Berlin']);

    expect($event->refresh()->timezone)->toBe('Europe/Berlin');
});

// ------------------------------------------------------------------
// Locale preference
// ------------------------------------------------------------------

it('saves each supported locale for the authenticated user', function (string $locale) {
    $user = makePreferencesUser();

    $response = $this->actingAs($user)->post(route('cabinet.preferences.locale'), [
        'locale' => $locale,
    ]);

    $response->assertRedirect(route('cabinet.tab', ['tab' => 'profile']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(LocalePreference::SESSION_KEY, $locale);

    expect($user->refresh()->locale)->toBe($locale);
})->with(['ru', 'en']);

it('flashes locale success in the newly selected language', function (string $previous, string $locale) {
    $user = makePreferencesUser(['locale' => $previous]);
    $message = trans('site.cabinet.flash.locale_saved', [], $locale);
    $oldMessage = trans('site.cabinet.flash.locale_saved', [], $previous);

    $this->actingAs($user)
        ->withSession([LocalePreference::SESSION_KEY => $previous])
        ->withUnencryptedCookie('locale', $previous)
        ->post(route('cabinet.preferences.locale'), ['locale' => $locale])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'profile']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(LocalePreference::SESSION_KEY, $locale)
        ->assertSessionHas('status', $message)
        ->assertPlainCookie('locale', $locale);

    expect($user->refresh()->locale)->toBe($locale)
        ->and(app()->getLocale())->toBe($locale);

    $this->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee($message)
        ->assertDontSee($oldMessage);
})->with([
    'RU to EN' => ['ru', 'en'],
    'EN to RU' => ['en', 'ru'],
]);

it('flashes automatic locale success in the browser language without the expired cookie', function (string $previous, string $browser) {
    $user = makePreferencesUser(['locale' => $previous]);
    $message = trans('site.cabinet.flash.locale_auto_saved', [], $browser);

    $this->actingAs($user)
        ->withSession([LocalePreference::SESSION_KEY => $previous])
        ->withUnencryptedCookie('locale', $previous)
        ->withHeader('Accept-Language', $browser)
        ->post(route('cabinet.preferences.locale'), ['locale' => ''])
        ->assertSessionHasNoErrors()
        ->assertSessionMissing(LocalePreference::SESSION_KEY)
        ->assertSessionHas('status', $message)
        ->assertCookieExpired('locale');

    expect($user->refresh()->locale)->toBeNull()
        ->and(app()->getLocale())->toBe($browser);
})->with([
    'EN to automatic RU' => ['en', 'ru'],
    'RU to automatic EN' => ['ru', 'en'],
]);

it('rejects an unsupported locale and keeps the stored value', function () {
    $user = makePreferencesUser(['locale' => 'ru']);

    $this->actingAs($user)
        ->post(route('cabinet.preferences.locale'), ['locale' => 'de'])
        ->assertSessionHasErrors('locale');

    expect($user->refresh()->locale)->toBe('ru');
});

it('rejects a locale longer than the column limit', function () {
    $user = makePreferencesUser();

    $this->actingAs($user)
        ->post(route('cabinet.preferences.locale'), ['locale' => str_repeat('a', 17)])
        ->assertSessionHasErrors('locale');

    expect($user->refresh()->locale)->toBeNull();
});

it('stores null and clears the session when the locale is set to auto', function () {
    $user = makePreferencesUser(['locale' => 'en']);

    $response = $this->actingAs($user)
        ->withSession([LocalePreference::SESSION_KEY => 'en'])
        ->post(route('cabinet.preferences.locale'), ['locale' => '']);

    $response->assertSessionHasNoErrors()
        ->assertSessionMissing(LocalePreference::SESSION_KEY);

    expect($user->refresh()->locale)->toBeNull();
});

it('does not change the session when the locale database write fails', function () {
    $user = makePreferencesUser();

    $session = Mockery::mock(Session::class);
    $session->shouldNotReceive('put');
    $this->app->instance(Session::class, $session);

    $mock = Mockery::mock(User::class)->makePartial();
    $mock->forceFill($user->getAttributes());
    $mock->shouldReceive('save')->once()->andThrow(failingQueryException());

    $response = $this->actingAs($mock)
        ->withSession([LocalePreference::SESSION_KEY => 'ru'])
        ->post(route('cabinet.preferences.locale'), ['locale' => 'en']);

    $response->assertSessionHasErrors('locale')
        ->assertSessionHas(LocalePreference::SESSION_KEY, 'ru');

    expect($user->refresh()->locale)->toBeNull();
});

it('keeps the database value when the session write fails after a successful save', function () {
    $user = makePreferencesUser();

    $session = Mockery::mock(Session::class);
    $session->shouldReceive('put')
        ->once()
        ->with(LocalePreference::SESSION_KEY, 'en')
        ->andThrow(new RuntimeException('session storage is unavailable'));

    $this->app->instance(Session::class, $session);

    $response = $this->actingAs($user)->post(route('cabinet.preferences.locale'), [
        'locale' => 'en',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertSessionHas('status')
        ->assertSessionMissing(LocalePreference::SESSION_KEY);

    expect($user->refresh()->locale)->toBe('en');
});

// ------------------------------------------------------------------
// Timezone preference
// ------------------------------------------------------------------

it('saves a valid IANA timezone', function (string $timezone) {
    $user = makePreferencesUser();

    $this->actingAs($user)
        ->post(route('cabinet.preferences.timezone'), ['timezone' => $timezone])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->timezone)->toBe($timezone);
})->with(['UTC', 'Europe/Moscow', 'Asia/Tokyo', 'America/New_York']);

it('rejects invalid or fixed-offset timezone values', function (string $timezone) {
    $user = makePreferencesUser(['timezone' => 'Europe/Moscow']);

    $this->actingAs($user)
        ->post(route('cabinet.preferences.timezone'), ['timezone' => $timezone])
        ->assertSessionHasErrors('timezone');

    expect($user->refresh()->timezone)->toBe('Europe/Moscow');
})->with([
    'Mars/Olympus',
    'UTC+3',
    '+03:00',
    'Europe',
    str_repeat('a', 65),
]);

it('stores null when the timezone is set to auto', function () {
    $user = makePreferencesUser(['timezone' => 'Asia/Tokyo']);

    $this->actingAs($user)
        ->post(route('cabinet.preferences.timezone'), ['timezone' => ''])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->timezone)->toBeNull();
});

it('keeps the stored timezone when the database write fails', function () {
    $user = makePreferencesUser(['timezone' => 'Europe/Moscow']);

    $mock = Mockery::mock(User::class)->makePartial();
    $mock->forceFill($user->getAttributes());
    $mock->shouldReceive('save')->once()->andThrow(failingQueryException());

    $response = $this->actingAs($mock)->post(route('cabinet.preferences.timezone'), [
        'timezone' => 'Asia/Tokyo',
    ]);

    $response->assertSessionHasErrors('timezone');

    expect($user->refresh()->timezone)->toBe('Europe/Moscow');
});

// ------------------------------------------------------------------
// Independence and existing forms
// ------------------------------------------------------------------

it('keeps locale and timezone independent', function () {
    $user = makePreferencesUser([
        'locale' => 'en',
        'timezone' => 'Europe/Moscow',
    ]);

    $this->actingAs($user)
        ->post(route('cabinet.preferences.locale'), ['locale' => 'ru'])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->locale)->toBe('ru')
        ->and($user->timezone)->toBe('Europe/Moscow');

    $this->actingAs($user)
        ->post(route('cabinet.preferences.timezone'), ['timezone' => 'Asia/Tokyo'])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->locale)->toBe('ru')
        ->and($user->timezone)->toBe('Asia/Tokyo');
});

it('does not reset locale or timezone when the main profile form is saved', function () {
    $user = makePreferencesUser([
        'locale' => 'en',
        'timezone' => 'Asia/Tokyo',
    ]);

    $this->actingAs($user)->post(route('cabinet.profile.update'), [
        'race' => 'hume',
        'main_job' => 'WHM',
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->race)->toBe('hume')
        ->and($user->main_job)->toBe('WHM')
        ->and($user->locale)->toBe('en')
        ->and($user->timezone)->toBe('Asia/Tokyo');
});

it('always updates the authenticated user and ignores user_id from the request', function () {
    $user = makePreferencesUser();
    $other = makePreferencesUser();

    $this->actingAs($user)->post(route('cabinet.preferences.locale'), [
        'locale' => 'en',
        'user_id' => $other->getKey(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('cabinet.preferences.timezone'), [
        'timezone' => 'Asia/Tokyo',
        'user_id' => $other->getKey(),
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->locale)->toBe('en')
        ->and($user->timezone)->toBe('Asia/Tokyo')
        ->and($other->refresh()->locale)->toBeNull()
        ->and($other->timezone)->toBeNull();
});

// ------------------------------------------------------------------
// Profile UI and access control
// ------------------------------------------------------------------

it('renders two independent preference forms and the L1 scope notice', function () {
    $user = makePreferencesUser([
        'locale' => 'en',
        'timezone' => 'Asia/Tokyo',
    ]);

    $response = $this->actingAs($user)->get(route('cabinet.tab', ['tab' => 'profile']));

    $response->assertOk()
        ->assertSee('Language and timezone')
        ->assertSee('applied immediately after saving')
        ->assertSee(route('cabinet.preferences.locale'), false)
        ->assertSee(route('cabinet.preferences.timezone'), false)
        ->assertSee('value="en" selected', false)
        ->assertSee('value="Asia/Tokyo" selected', false);
});

it('renders the database values even when the session contains another locale', function () {
    $user = makePreferencesUser([
        'locale' => 'en',
        'timezone' => 'Asia/Tokyo',
    ]);

    $response = $this->actingAs($user)
        ->withSession([LocalePreference::SESSION_KEY => 'ru'])
        ->get(route('cabinet.tab', ['tab' => 'profile']));

    $response->assertOk()
        ->assertSee('value="en" selected', false)
        ->assertSee('value="Asia/Tokyo" selected', false);
});

it('redirects guests from both preference routes', function () {
    $this->post(route('cabinet.preferences.locale'), ['locale' => 'en'])
        ->assertRedirect(route('login'));

    $this->post(route('cabinet.preferences.timezone'), ['timezone' => 'UTC'])
        ->assertRedirect(route('login'));
});

it('requires a verified email for preference updates', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->post(route('cabinet.preferences.locale'), ['locale' => 'en'])
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->post(route('cabinet.preferences.timezone'), ['timezone' => 'UTC'])
        ->assertRedirect(route('verification.notice'));

    $user->refresh();
    expect($user->locale)->toBeNull()
        ->and($user->timezone)->toBeNull();
});
