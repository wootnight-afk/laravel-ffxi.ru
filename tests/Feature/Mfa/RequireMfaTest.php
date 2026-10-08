<?php

declare(strict_types=1);

use App\Http\Middleware\RequireMfa;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

const ENFORCE_SECRET = 'JBSWY3DPEHPK3PXP';

function mfaEnforcedUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function withMfaConfigured(User $user, ?string $secret = ENFORCE_SECRET): User
{
    $user->saveAppAuthenticationSecret($secret);

    return $user->refresh();
}

function markMfaVerified(User $user): void
{
    test()->actingAs($user)->withSession([
        RequireMfa::SESSION_USER_ID_KEY => $user->getKey(),
        RequireMfa::SESSION_VERIFIED_AT_KEY => now()->timestamp,
    ]);
}

beforeEach(function () {
    Cache::flush();
});

it('registers RequireMfa as persistent Livewire middleware', function () {
    // Livewire interactions bypass route middleware, so enforcement relies on
    // the persistent middleware list (ADR-009 §2.3).
    expect(Livewire::getPersistentMiddleware())->toContain(RequireMfa::class);
});

// ------------------------------------------------------------------
// /players
// ------------------------------------------------------------------

it('lets a user without MFA into /players (opt-in)', function () {
    $user = mfaEnforcedUser('user');

    actingAs($user)->get('/players')->assertOk();
});

it('redirects a user with unverified MFA away from /players', function () {
    $user = withMfaConfigured(mfaEnforcedUser('user'));

    actingAs($user)
        ->get('/players')
        ->assertRedirect(route('mfa.challenge'));
});

it('lets a verified user into /players', function () {
    $user = withMfaConfigured(mfaEnforcedUser('user'));

    markMfaVerified($user);
    get('/players')->assertOk();
});

it('protects every /players route, including directory and profile', function () {
    $user = withMfaConfigured(mfaEnforcedUser('user'));

    $target = mfaEnforcedUser('user');

    actingAs($user)->get('/players/directory')->assertRedirect(route('mfa.challenge'));
    actingAs($user)->get('/players/'.$target->name)->assertRedirect(route('mfa.challenge'));
});

it('does not leak another user verification state (T24)', function () {
    $verified = withMfaConfigured(mfaEnforcedUser('user'));
    $other = withMfaConfigured(mfaEnforcedUser('user'));

    // Session carries the verification of a different user id.
    actingAs($other)->withSession([
        RequireMfa::SESSION_USER_ID_KEY => $verified->getKey(),
    ]);

    get('/players')->assertRedirect(route('mfa.challenge'));
});

it('does not protect /activity (outside the matrix)', function () {
    $user = withMfaConfigured(mfaEnforcedUser('user'));

    actingAs($user)->get('/activity')->assertOk();
});

it('keeps /cabinet/security reachable without MFA', function () {
    $user = withMfaConfigured(mfaEnforcedUser('user'));

    actingAs($user)->get('/cabinet/security')->assertOk();
});

// ------------------------------------------------------------------
// Global gate
// ------------------------------------------------------------------

it('disables enforcement for everyone when the global gate is off', function () {
    app(SettingsRepository::class)->set('mfa_global_enabled', false);

    $user = withMfaConfigured(mfaEnforcedUser('user'));
    $admin = withMfaConfigured(mfaEnforcedUser('admin'));

    actingAs($user)->get('/players')->assertOk();
    actingAs($admin)->get('/admin/users')->assertOk();
});

// ------------------------------------------------------------------
// Admin panel
// ------------------------------------------------------------------

it('redirects an admin with unverified MFA from the panel to the challenge', function () {
    $admin = withMfaConfigured(mfaEnforcedUser('admin'));

    actingAs($admin)
        ->get('/admin/users')
        ->assertRedirect(route('mfa.challenge'));
});

it('lets an admin with verified MFA into the panel', function () {
    $admin = withMfaConfigured(mfaEnforcedUser('admin'));

    markMfaVerified($admin);
    get('/admin/users')->assertOk();
});

it('sends a required admin without MFA to the cabinet security page', function () {
    app(SettingsRepository::class)->set('admin_2fa_required', true);
    $admin = mfaEnforcedUser('admin');

    actingAs($admin)
        ->get('/admin/users')
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']));
});

it('keeps /admin/settings reachable for a required admin without MFA', function () {
    app(SettingsRepository::class)->set('admin_2fa_required', true);
    $admin = mfaEnforcedUser('admin');

    actingAs($admin)->get('/admin/settings')->assertOk();
});

it('does not require MFA from an admin when admin_2fa_required is off', function () {
    app(SettingsRepository::class)->set('admin_2fa_required', false);
    $admin = mfaEnforcedUser('admin');

    actingAs($admin)->get('/admin/users')->assertOk();
});

it('never enforces admin_2fa_required on editors', function () {
    app(SettingsRepository::class)->set('admin_2fa_required', true);
    $editor = mfaEnforcedUser('editor');

    actingAs($editor)->get('/admin/news')->assertOk();
});

it('redirects an editor with unverified MFA from content admin routes', function () {
    $editor = withMfaConfigured(mfaEnforcedUser('editor'));

    actingAs($editor)
        ->get('/admin/news')
        ->assertRedirect(route('mfa.challenge'));
});

it('exempts the editor profile page from MFA enforcement', function () {
    $editor = withMfaConfigured(mfaEnforcedUser('editor'));

    actingAs($editor)->get('/admin/profile')->assertOk();
});

it('keeps /admin/logout reachable for an admin with unverified MFA', function () {
    $admin = withMfaConfigured(mfaEnforcedUser('admin'));

    $response = actingAs($admin)->post('/admin/logout');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('mfa/challenge');
});
