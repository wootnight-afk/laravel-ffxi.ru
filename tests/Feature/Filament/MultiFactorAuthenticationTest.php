<?php

declare(strict_types=1);

use App\Http\Middleware\RequireMfa;
use App\Models\User;
use App\Services\SettingsRepository;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function makeMfaPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('keeps app authentication optional by default', function () {
    $admin = makeMfaPanelUser('admin');

    expect(app(SettingsRepository::class)->bool('admin_2fa_required'))->toBeFalse()
        ->and(AppAuthentication::make()->isEnabled($admin))->toBeFalse();

    $this->actingAs($admin)->get('/admin/users')->assertOk();
});

it('offers the app authentication setup action on the profile page', function () {
    $admin = makeMfaPanelUser('admin');

    $this->actingAs($admin)
        ->get('/admin/profile')
        ->assertOk()
        ->assertSee('2FA-приложение')
        ->assertSee('Включить');
});

it('redirects a required admin without MFA to the cabinet security page', function () {
    $admin = makeMfaPanelUser('admin');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    // Unified enforcement (ADR-009 §2.3) runs before the legacy Filament
    // middleware and points the admin at the self-service setup page.
    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertRedirect('/cabinet/security');
});

it('keeps the admin settings escape-hatch reachable without app authentication', function () {
    $admin = makeMfaPanelUser('admin');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    // ADR-009 §2.4: /admin/settings must stay reachable so an admin can manage
    // the global flags and never lock themselves out.
    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertOk();
});

it('does not loop between unified enforcement and the Filament setup page', function () {
    $admin = makeMfaPanelUser('admin');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    // The Filament setup route is now shadowed by unified enforcement; the
    // admin without MFA is sent to the self-service page, never back to setup.
    $this->actingAs($admin)
        ->get('/admin/multi-factor-authentication/set-up')
        ->assertRedirect('/cabinet/security');
});

it('keeps the cabinet security escape-hatch outside the panel MFA middleware', function () {
    $admin = makeMfaPanelUser('admin');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    // ADR-009 §2.4: /cabinet/security is a web route, not part of the panel
    // MFA middleware, and must stay reachable without completed MFA.
    $this->actingAs($admin)
        ->get('/cabinet/security')
        ->assertOk();
});

it('never requires app authentication from editors', function () {
    $editor = makeMfaPanelUser('editor');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    $this->actingAs($editor)->get('/admin/news')->assertOk();
});

it('lets a required admin through once MFA is configured and verified', function () {
    $admin = makeMfaPanelUser('admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    expect(AppAuthentication::make()->isEnabled($admin->refresh()))->toBeTrue();

    $this->actingAs($admin)
        ->withSession([RequireMfa::SESSION_USER_ID_KEY => $admin->getKey()])
        ->get('/admin/users')
        ->assertOk();
});

it('stores app authentication secrets encrypted and hidden', function () {
    $admin = makeMfaPanelUser('admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $admin->saveAppAuthenticationRecoveryCodes([Hash::make('recovery-code-1')]);

    $raw = DB::table('users')->where('id', $admin->getKey())->first();

    expect($raw->app_authentication_secret)->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($raw->app_authentication_recovery_codes)->not->toContain('recovery-code-1');

    $array = $admin->refresh()->toArray();

    expect($array)->not->toHaveKey('app_authentication_secret')
        ->and($array)->not->toHaveKey('app_authentication_recovery_codes');

    $codes = $admin->getAppAuthenticationRecoveryCodes();

    expect($admin->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($codes)->toHaveCount(1)
        ->and(Hash::check('recovery-code-1', $codes[0]))->toBeTrue();
});
