<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

function prodMfaUser(string $role, bool $withMfa = false): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    if ($withMfa) {
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    }

    return $user->refresh();
}

beforeEach(function () {
    Cache::flush();
    $this->originalEnvironment = app()->environment();
});

afterEach(function () {
    app()->detectEnvironment(fn () => $this->originalEnvironment);
});

// ------------------------------------------------------------------
// Production invariant — enforcement cannot be weakened
// ------------------------------------------------------------------

it('blocks an admin without MFA in production even when admin_2fa_required is off', function () {
    app(SettingsRepository::class)->set('admin_2fa_required', false);
    app()->detectEnvironment(fn () => 'production');

    $admin = prodMfaUser('admin');

    actingAs($admin)
        ->get('/admin/users')
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']));
});

it('keeps enforcing MFA in production when mfa_global_enabled is off', function () {
    app(SettingsRepository::class)->set('mfa_global_enabled', false);
    app()->detectEnvironment(fn () => 'production');

    $admin = prodMfaUser('admin', withMfa: true);

    actingAs($admin)
        ->get('/admin/users')
        ->assertRedirect(route('mfa.challenge'));
});

it('blocks a required admin without MFA in production even when both flags are off', function () {
    app(SettingsRepository::class)->set('mfa_global_enabled', false);
    app(SettingsRepository::class)->set('admin_2fa_required', false);
    app()->detectEnvironment(fn () => 'production');

    $admin = prodMfaUser('admin');

    actingAs($admin)
        ->get('/admin/users')
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']));
});

// ------------------------------------------------------------------
// Escape-hatches stay reachable in production
// ------------------------------------------------------------------

it('keeps the admin settings escape-hatch reachable in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $admin = prodMfaUser('admin');

    actingAs($admin)->get('/admin/settings')->assertOk();
});

it('keeps the cabinet security escape-hatch reachable in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $admin = prodMfaUser('admin');

    actingAs($admin)->get('/cabinet/security')->assertOk();
});

// ------------------------------------------------------------------
// Non-production — dev behaviour preserved (regression)
// ------------------------------------------------------------------

it('lets an admin without MFA into the panel outside production', function () {
    app(SettingsRepository::class)->set('admin_2fa_required', false);

    $admin = prodMfaUser('admin');

    actingAs($admin)->get('/admin/users')->assertOk();
});

it('still disables enforcement outside production when the global gate is off', function () {
    app(SettingsRepository::class)->set('mfa_global_enabled', false);

    $admin = prodMfaUser('admin', withMfa: true);

    actingAs($admin)->get('/admin/users')->assertOk();
});
