<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsRepository;

function makeAllowlistPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('allows every IP when the allowlist is empty', function () {
    expect(app(SettingsRepository::class)->get('admin_ip_allowlist'))->toBe([]);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get('/admin/login')
        ->assertOk();
});

it('allows a login attempt from an IP inside the allowlist', function () {
    app(SettingsRepository::class)->set('admin_ip_allowlist', ['203.0.113.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get('/admin/login')
        ->assertOk();
});

it('blocks a login attempt from an IP outside the allowlist', function () {
    app(SettingsRepository::class)->set('admin_ip_allowlist', ['203.0.113.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->get('/admin/login')
        ->assertForbidden();
});

it('matches addresses inside a configured IPv4 CIDR subnet', function () {
    app(SettingsRepository::class)->set('admin_ip_allowlist', ['192.168.10.0/24']);

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.55'])
        ->get('/admin/login')
        ->assertOk();

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.11.55'])
        ->get('/admin/login')
        ->assertForbidden();
});

it('matches addresses inside a configured IPv6 CIDR subnet', function () {
    app(SettingsRepository::class)->set('admin_ip_allowlist', ['2001:db8::/32']);

    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::1'])
        ->get('/admin/login')
        ->assertOk();

    $this->withServerVariables(['REMOTE_ADDR' => '2001:db9::1'])
        ->get('/admin/login')
        ->assertForbidden();
});

it('protects authenticated panel routes as well', function () {
    $admin = makeAllowlistPanelUser('admin');
    app(SettingsRepository::class)->set('admin_ip_allowlist', ['203.0.113.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->actingAs($admin)
        ->get('/admin/users')
        ->assertForbidden();
});
