<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Regression: admin panel logout loop on Livewire updates
|--------------------------------------------------------------------------
|
| The panel used to register the whole web middleware stack — including
| EncryptCookies and StartSession — as Livewire persistent middleware. Livewire
| re-applies persistent middleware to a fake request built from
| Request::duplicate(), whose cookies are already decrypted. Re-running
| EncryptCookies therefore double-decrypted them and set every encrypted cookie
| to null, so AuthenticateSession saw a null remember_web_* cookie while
| viaRemember() was true and logged the user out on the next Livewire update.
|
| MFA is intentionally not involved in this scenario.
|
*/

function rememberAdminUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');
    $user->forceFill(['remember_token' => Str::random(60)])->save();

    return $user->refresh();
}

/**
 * Build the raw (unencrypted) value of the "remember me" cookie. The test
 * request helper encrypts it with the cookie value prefix, mirroring what the
 * browser sends and what EncryptCookies decrypts on the way in.
 */
function rememberCookieValueFor(User $user): string
{
    return implode('|', [
        $user->getAuthIdentifier(),
        $user->getRememberToken(),
        Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword()),
    ]);
}

it('keeps a remember-cookie session alive across a Livewire update', function () {
    $admin = rememberAdminUser();

    // A genuine snapshot from the panel dashboard (memo.path = /admin) so the
    // panel middleware set is what gets re-applied to the update request.
    $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $match);
    $snapshot = html_entity_decode($match[1], ENT_QUOTES);

    // The session carries no authenticated user, so the guard has to
    // authenticate through the remember cookie (viaRemember() === true) — the
    // failing path. The session still holds the CSRF token so the update
    // request is not rejected by the CSRF middleware first.
    app('auth')->forgetGuards();

    $rememberName = Auth::guard('web')->getRecallerName();

    $response = $this->withCredentials()
        ->withSession(['_token' => 'test-token'])
        ->withCookie($rememberName, rememberCookieValueFor($admin))
        ->postJson(Livewire::getUpdateUri(), [
            '_token' => 'test-token',
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) [],
                'calls' => [],
            ]],
        ], ['X-Livewire' => 'true']);

    $response->assertOk();

    $rememberWasCleared = collect($response->headers->getCookies())
        ->contains(fn ($cookie) => $cookie->getName() === $rememberName
            && $cookie->getExpiresTime() <= time());

    expect($rememberWasCleared)->toBeFalse();
});

it('does not re-apply the cookie and session stack as Livewire persistent middleware', function () {
    $persistent = Livewire::getPersistentMiddleware();

    expect($persistent)->not->toContain(EncryptCookies::class)
        ->not->toContain(AddQueuedCookiesToResponse::class)
        ->not->toContain(StartSession::class);
});
