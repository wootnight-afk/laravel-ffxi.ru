<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\LocaleResolver;
use Illuminate\Support\Facades\DB;

function localeTestUser(array $overrides = []): User
{
    return User::factory()->create($overrides);
}

// ------------------------------------------------------------------
// Guest — session → cookie → Accept-Language → ru
// ------------------------------------------------------------------

it('falls back to ru for a guest without any hint', function () {
    // The test client sends a default Accept-Language header; clear it so the
    // fallback chain ends at the site default.
    $this->withHeader('Accept-Language', '')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="ru">', false);
});

it('uses the locale cookie for a guest', function () {
    $this->withUnencryptedCookie(LocaleResolver::COOKIE, 'en');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('prefers the session choice over the cookie for a guest', function () {
    $this->withSession(['locale' => 'en'])
        ->withUnencryptedCookie(LocaleResolver::COOKIE, 'ru');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('uses Accept-Language as a guest hint', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('matches a primary Accept-Language subtag without a base tag', function () {
    $this->withHeader('Accept-Language', 'en-US');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('orders Accept-Language candidates by quality', function () {
    $this->withHeader('Accept-Language', 'ru;q=0.4,en;q=0.8');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('ignores unsupported Accept-Language values', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="ru">', false);
});

it('ignores a cookie with an unsupported locale', function () {
    $this->withUnencryptedCookie(LocaleResolver::COOKIE, 'xx')
        ->withHeader('Accept-Language', '')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="ru">', false);
});

it('ignores an unsupported session value', function () {
    $this->withSession(['locale' => 'de'])
        ->withHeader('Accept-Language', '')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="ru">', false);
});

// ------------------------------------------------------------------
// Authenticated — users.locale → session → cookie → Accept-Language → ru
// ------------------------------------------------------------------

it('prefers the stored user locale over session and cookie', function () {
    $user = localeTestUser(['locale' => 'en']);

    $this->actingAs($user)
        ->withSession(['locale' => 'ru'])
        ->withUnencryptedCookie(LocaleResolver::COOKIE, 'ru');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('falls back to the session when the stored user locale is empty', function () {
    $user = localeTestUser(['locale' => null]);

    $this->actingAs($user)->withSession(['locale' => 'en']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('falls back to the cookie when user and session are empty', function () {
    $user = localeTestUser(['locale' => null]);

    $this->actingAs($user)->withUnencryptedCookie(LocaleResolver::COOKIE, 'en');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('ignores an invalid stored user locale and continues down the chain', function () {
    $user = localeTestUser(['locale' => null]);

    DB::table('users')->where('id', $user->id)->update(['locale' => 'xx']);
    $user->refresh();

    $this->actingAs($user)->withUnencryptedCookie(LocaleResolver::COOKIE, 'en');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

// ------------------------------------------------------------------
// Resolver contract
// ------------------------------------------------------------------

it('resolves ru without an http request', function () {
    $resolver = app(LocaleResolver::class);

    expect($resolver->resolve())->toBe('ru');
});

it('renders the language switcher for the resolved locale', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('locale-switcher', false)
        ->assertSee('action="'.route('locale.update').'"', false)
        ->assertSee('aria-current="true"', false);
});
