<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function makeGateAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

function makeGateUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// --- Contract 1: scoped section bypass (probe ability) ---
it('lets admin pass arbitrary section-prefixed abilities via Gate::before', function () {
    $admin = makeGateAdmin();

    expect(Gate::forUser($admin)->allows('section.probe_xyz.view'))->toBeTrue();
});

// --- Contract 1b: scoped section bypass survives permission revoke ---
it('still bypasses section abilities after the underlying permission is revoked', function () {
    $admin = makeGateAdmin();

    // Sanity: before revoke, admin has the permission via the role.
    expect($admin->hasPermissionTo('section.players.view'))->toBeTrue();

    Role::findByName('admin')->revokePermissionTo('section.players.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = $admin->fresh();

    // Sanity: after revoke, the permission is really gone.
    // hasPermissionTo() checks Spatie directly, bypassing the Gate layer,
    // so this is not affected by Gate::before.
    expect($admin->hasPermissionTo('section.players.view'))->toBeFalse();

    // Yet Gate still allows it — proving that only Gate::before's section.*
    // bypass can be responsible.
    expect(Gate::forUser($admin)->allows('section.players.view'))->toBeTrue();
});

// --- Contract 2: no global admin bypass ---
it('does not globally bypass non-section abilities for admin', function () {
    $admin = makeGateAdmin();

    // Regression probe for the scoped shape of Gate::before in AppServiceProvider.
    // This is not a claim about any specific application ability; it locks the
    // architectural contract that admin bypass is limited to section.* and
    // does NOT globally short-circuit the Gate.
    expect(Gate::forUser($admin)->allows('nonexistent.probe_xyz'))->toBeFalse();
});

// --- Contract 3: user policy self-delete restriction preserved for admin ---
it('preserves the self-delete restriction in UserPolicy for admin', function () {
    $admin = makeGateAdmin();

    expect(Gate::forUser($admin)->allows('delete', $admin))->toBeFalse();
});

// --- Contract 4: permission-based ability not delivered via bypass ---
it('resolves news.moderate through permissions, not via global bypass', function () {
    $admin = makeGateAdmin();
    $user = makeGateUser();

    expect(Gate::forUser($admin)->allows('news.moderate'))->toBeTrue();
    expect(Gate::forUser($user)->allows('news.moderate'))->toBeFalse();
});
