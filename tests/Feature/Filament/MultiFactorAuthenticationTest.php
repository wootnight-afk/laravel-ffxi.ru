<?php

declare(strict_types=1);

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

it('redirects a required admin without app authentication to the setup page', function () {
    $admin = makeMfaPanelUser('admin');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertRedirect('/admin/multi-factor-authentication/set-up');
});

it('never requires app authentication from editors', function () {
    $editor = makeMfaPanelUser('editor');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    $this->actingAs($editor)->get('/admin/news')->assertOk();
});

it('lets a required admin through once app authentication is enabled', function () {
    $admin = makeMfaPanelUser('admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    app(SettingsRepository::class)->set('admin_2fa_required', true);

    expect(AppAuthentication::make()->isEnabled($admin->refresh()))->toBeTrue();

    $this->actingAs($admin)->get('/admin/users')->assertOk();
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
