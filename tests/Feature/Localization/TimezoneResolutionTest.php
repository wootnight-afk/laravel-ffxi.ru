<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\TimezoneResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function timezoneTestRequest(array $cookies = []): Request
{
    $request = Request::create('/');

    foreach ($cookies as $name => $value) {
        $request->cookies->set($name, $value);
    }

    return $request;
}

// ------------------------------------------------------------------
// users.timezone → cookie tz → UTC
// ------------------------------------------------------------------

it('resolves utc without an http request', function () {
    $resolver = app(TimezoneResolver::class);

    expect($resolver->resolve())->toBe('UTC')
        ->and($resolver->isFallback())->toBeTrue();
});

it('uses the tz cookie for a guest', function () {
    $this->app->instance('request', timezoneTestRequest([TimezoneResolver::COOKIE => 'Europe/Moscow']));

    $resolver = app(TimezoneResolver::class);

    expect($resolver->resolve())->toBe('Europe/Moscow')
        ->and($resolver->isFallback())->toBeFalse();
});

it('decodes an url-encoded cookie value', function () {
    $this->app->instance('request', timezoneTestRequest([TimezoneResolver::COOKIE => 'Europe%2FMoscow']));

    expect(app(TimezoneResolver::class)->resolve())->toBe('Europe/Moscow');
});

it('prefers the stored user timezone over the cookie', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

    $this->app->instance('request', timezoneTestRequest([TimezoneResolver::COOKIE => 'Europe/Moscow']));

    $resolver = app(TimezoneResolver::class);

    expect($resolver->resolve($user))->toBe('Asia/Tokyo')
        ->and($resolver->isFallback())->toBeFalse();
});

it('ignores an invalid stored timezone and uses the cookie', function () {
    $user = User::factory()->create(['timezone' => null]);

    DB::table('users')->where('id', $user->id)->update(['timezone' => 'Mars/Base']);
    $user->refresh();

    $this->app->instance('request', timezoneTestRequest([TimezoneResolver::COOKIE => 'Europe/Berlin']));

    expect(app(TimezoneResolver::class)->resolve($user))->toBe('Europe/Berlin');
});

it('ignores fixed offsets and falls back to utc', function () {
    $this->app->instance('request', timezoneTestRequest([TimezoneResolver::COOKIE => 'UTC+3']));

    $resolver = app(TimezoneResolver::class);

    expect($resolver->resolve())->toBe('UTC')
        ->and($resolver->isFallback())->toBeTrue();
});

it('ignores an empty cookie value', function () {
    $this->app->instance('request', timezoneTestRequest([TimezoneResolver::COOKIE => '']));

    expect(app(TimezoneResolver::class)->resolve())->toBe('UTC');
});

it('treats an explicit utc cookie as a real choice', function () {
    $this->app->instance('request', timezoneTestRequest([TimezoneResolver::COOKIE => 'UTC']));

    $resolver = app(TimezoneResolver::class);

    expect($resolver->resolve())->toBe('UTC')
        ->and($resolver->isFallback())->toBeFalse();
});

// ------------------------------------------------------------------
// HTTP integration
// ------------------------------------------------------------------

it('reads the plain tz cookie on a real request', function () {
    $this->withUnencryptedCookie(TimezoneResolver::COOKIE, 'Europe/Berlin')
        ->get(route('home'))
        ->assertOk();

    expect(app(TimezoneResolver::class)->resolve())->toBe('Europe/Berlin');
});

it('never caches the timezone in the session', function () {
    $this->withUnencryptedCookie(TimezoneResolver::COOKIE, 'Europe/Berlin')
        ->get(route('home'))
        ->assertOk();

    expect(session()->has('tz'))->toBeFalse()
        ->and(session()->has('timezone'))->toBeFalse();
});

it('keeps the application timezone at utc', function () {
    expect(config('app.timezone'))->toBe('UTC');
});

// ------------------------------------------------------------------
// Container semantics
// ------------------------------------------------------------------

it('shares one resolver instance so isFallback matches the last resolve', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

    $this->app->instance('request', timezoneTestRequest());

    expect(app(TimezoneResolver::class))->toBe(app(TimezoneResolver::class));

    // resolve() through one container lookup, isFallback() through another:
    // both must describe the same resolution (D2 from the L2 audit).
    expect(app(TimezoneResolver::class)->resolve($user))->toBe('Asia/Tokyo')
        ->and(app(TimezoneResolver::class)->isFallback())->toBeFalse();
});

it('reports the fallback flag of the latest resolve call', function () {
    $resolver = app(TimezoneResolver::class);

    $this->app->instance('request', timezoneTestRequest([TimezoneResolver::COOKIE => 'Europe/Berlin']));
    expect($resolver->resolve())->toBe('Europe/Berlin')
        ->and($resolver->isFallback())->toBeFalse();

    $this->app->instance('request', timezoneTestRequest());
    expect($resolver->resolve())->toBe('UTC')
        ->and($resolver->isFallback())->toBeTrue();
});
