<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\LocaleResolver;

function localeSwitchUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'email_verified_at' => now(),
    ], $overrides));
}

// ------------------------------------------------------------------
// Guest — session + cookie
// ------------------------------------------------------------------

it('switches the language for a guest and stores session and cookie', function () {
    $response = $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'en']);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('locale', 'en');

    $cookie = $response->getCookie(LocaleResolver::COOKIE, false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe('en')
        ->and($cookie->getPath())->toBe('/')
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addMonths(11)->getTimestamp());

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('rejects an unsupported locale without touching the session', function () {
    $response = $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'xx']);

    $response->assertSessionHasErrors('locale');

    expect(session('locale'))->toBeNull()
        ->and($response->getCookie(LocaleResolver::COOKIE, false))->toBeNull();
});

it('rejects a missing locale', function () {
    $response = $this->from(route('home'))->post(route('locale.update'), []);

    $response->assertSessionHasErrors('locale');

    expect(session('locale'))->toBeNull();
});

// ------------------------------------------------------------------
// Authenticated — users.locale first, then session and cookie
// ------------------------------------------------------------------

it('stores the language in the profile and mirrors it into session and cookie', function () {
    $user = localeSwitchUser(['locale' => null]);

    $response = $this->actingAs($user)
        ->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'en']);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('locale', 'en');

    expect($user->refresh()->locale)->toBe('en')
        ->and($response->getCookie(LocaleResolver::COOKIE, false)?->getValue())->toBe('en');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

it('keeps session and cookie untouched when the profile write fails', function () {
    $user = localeSwitchUser(['locale' => null]);

    User::saving(function (): void {
        throw new RuntimeException('database is unavailable');
    });

    $response = $this->actingAs($user)
        ->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'en']);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors('locale');

    expect(session('locale'))->toBeNull()
        ->and($response->getCookie(LocaleResolver::COOKIE, false))->toBeNull()
        ->and($user->refresh()->locale)->toBeNull();
});

it('removes the stale locale cookie when the user selects auto', function () {
    $user = localeSwitchUser(['locale' => 'en']);

    $response = $this->actingAs($user)
        ->from(route('cabinet.tab', ['tab' => 'profile']))
        ->post(route('cabinet.preferences.locale'), ['locale' => '']);

    $response->assertRedirect(route('cabinet.tab', ['tab' => 'profile']));

    expect($user->refresh()->locale)->toBeNull();

    $cookie = $response->getCookie(LocaleResolver::COOKIE, false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBeNull()
        ->and($cookie->getExpiresTime())->toBeLessThan(time());
});

it('applies the stored locale to the admin panel', function () {
    $admin = localeSwitchUser(['locale' => 'en']);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee('filament.navigation');

    expect(app()->getLocale())->toBe('en');
});

it('throttles the switch endpoint', function () {
    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->from(route('home'))
            ->post(route('locale.update'), ['locale' => 'ru'])
            ->assertRedirect(route('home'));
    }

    $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'ru'])
        ->assertStatus(429);
});
