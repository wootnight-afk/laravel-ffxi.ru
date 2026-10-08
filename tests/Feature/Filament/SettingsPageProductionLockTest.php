<?php

declare(strict_types=1);

use App\Filament\Pages\SettingsPage;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function settingsLockAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

beforeEach(function () {
    Cache::flush();
    $this->originalEnvironment = app()->environment();
});

afterEach(function () {
    app()->detectEnvironment(fn () => $this->originalEnvironment);
});

// ------------------------------------------------------------------
// Production — UI lock
// ------------------------------------------------------------------

it('disables both MFA toggles in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $admin = settingsLockAdmin();

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->assertFormFieldDisabled('mfa_global_enabled')
        ->assertFormFieldDisabled('admin_2fa_required');
});

it('shows the production lock hint in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $admin = settingsLockAdmin();

    actingAs($admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('Заблокировано в production');
});

// ------------------------------------------------------------------
// Production — server-side guard
// ------------------------------------------------------------------

it('does not persist weakened MFA settings in production', function () {
    app(SettingsRepository::class)->set('mfa_global_enabled', true);
    app(SettingsRepository::class)->set('admin_2fa_required', true);
    app()->detectEnvironment(fn () => 'production');

    $admin = settingsLockAdmin();

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.mfa_global_enabled', false)
        ->set('data.admin_2fa_required', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->bool('mfa_global_enabled'))->toBeTrue()
        ->and(app(SettingsRepository::class)->bool('admin_2fa_required'))->toBeTrue();
});

// ------------------------------------------------------------------
// Non-production — behaviour preserved
// ------------------------------------------------------------------

it('still allows changing the global MFA toggle outside production', function () {
    $admin = settingsLockAdmin();

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->assertFormFieldEnabled('mfa_global_enabled')
        ->set('data.mfa_global_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->bool('mfa_global_enabled'))->toBeFalse();
});

it('still allows changing the admin MFA requirement outside production', function () {
    $admin = settingsLockAdmin();

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->assertFormFieldEnabled('admin_2fa_required')
        ->set('data.admin_2fa_required', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->bool('admin_2fa_required'))->toBeTrue();
});
