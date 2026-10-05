<?php

declare(strict_types=1);

use App\Filament\Pages\SettingsPage;
use App\Models\User;
use App\Services\SettingsRepository;
use Livewire\Livewire;

function makeSettingsPageUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('allows admins to access settings and renders the settings groups', function () {
    $admin = makeSettingsPageUser('admin');

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('Общие')
        ->assertSee('Регистрация')
        ->assertSee('Комментарии')
        ->assertSee('Чат')
        ->assertSee('События')
        ->assertSee('Галерея и профиль')
        ->assertSee('Безопасность')
        ->assertSee('Активность');
});

it('denies editor access to settings by direct URL', function () {
    $editor = makeSettingsPageUser('editor');

    $this->actingAs($editor)->get('/admin/settings')->assertForbidden();
});

it('saves known setting values and makes them immediately available from the repository', function () {
    $admin = makeSettingsPageUser('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.registration_open', true)
        ->set('data.guest_sections.news', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->bool('registration_open'))->toBeTrue()
        ->and(app(SettingsRepository::class)->get('guest_sections')['news'])->toBeFalse();
});

it('does not persist unrecognized submitted keys', function () {
    $admin = makeSettingsPageUser('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.unknown_admin_setting', 'unexpected')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('unknown_admin_setting'))->toBeNull();
});

it('keeps admin MFA optional by default', function () {
    expect(app(SettingsRepository::class)->bool('admin_2fa_required'))->toBeFalse();
});

it('rejects values outside explicit numeric bounds without saving any settings', function () {
    $admin = makeSettingsPageUser('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.registration_open', true)
        ->set('data.chat_polling_interval', 2)
        ->call('save')
        ->assertHasErrors('data.chat_polling_interval');

    expect(app(SettingsRepository::class)->bool('registration_open'))->toBeFalse();
});

it('rejects malformed IP or CIDR entries without persisting them', function () {
    $admin = makeSettingsPageUser('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.admin_ip_allowlist_text', '192.168.1.0/99')
        ->call('save')
        ->assertHasErrors('data.admin_ip_allowlist_text');

    expect(app(SettingsRepository::class)->get('admin_ip_allowlist'))->toBe([]);
});

it('requires re-authentication before changing the IP allowlist', function () {
    $admin = makeSettingsPageUser('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.admin_ip_allowlist_text', '192.168.1.0/24')
        ->call('save')
        ->assertHasErrors('data.reauth_password');

    expect(app(SettingsRepository::class)->get('admin_ip_allowlist'))->toBe([]);
});

it('rejects an incorrect password when changing the IP allowlist', function () {
    $admin = makeSettingsPageUser('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.admin_ip_allowlist_text', '192.168.1.0/24')
        ->set('data.reauth_password', 'wrong-password')
        ->call('save')
        ->assertHasErrors('data.reauth_password');

    expect(app(SettingsRepository::class)->get('admin_ip_allowlist'))->toBe([]);
});

it('saves a validated CIDR allowlist after password confirmation', function () {
    $admin = makeSettingsPageUser('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.admin_ip_allowlist_text', "192.168.1.0/24\n2001:db8::/32")
        ->set('data.reauth_password', 'password')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('admin_ip_allowlist'))
        ->toBe(['192.168.1.0/24', '2001:db8::/32']);
});

it('stores an empty IP allowlist as an empty JSON array, meaning allow all', function () {
    $admin = makeSettingsPageUser('admin');

    app(SettingsRepository::class)->set('admin_ip_allowlist', ['192.168.1.0/24']);

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.admin_ip_allowlist_text', '')
        ->set('data.reauth_password', 'password')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('admin_ip_allowlist'))->toBe([]);
});

it('rejects invalid timezones without partially saving other fields', function () {
    $admin = makeSettingsPageUser('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.registration_open', true)
        ->set('data.timezone_display', 'Mars/Olympus')
        ->call('save')
        ->assertHasErrors('data.timezone_display');

    expect(app(SettingsRepository::class)->bool('registration_open'))->toBeFalse();
});
