<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function makeAdminPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

// ------------------------------------------------------------------
// Access by role
// ------------------------------------------------------------------

it('redirects guests from admin panel to login', function () {
    get('/admin')->assertRedirect('/admin/login');
});

it('forbids regular users from admin panel', function () {
    $user = makeAdminPanelUser('user');

    actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('allows editors to access admin panel', function () {
    $user = makeAdminPanelUser('editor');

    actingAs($user)
        ->get('/admin')
        ->assertOk();
});

it('allows admins to access admin panel', function () {
    $user = makeAdminPanelUser('admin');

    actingAs($user)
        ->get('/admin')
        ->assertOk();
});

// ------------------------------------------------------------------
// Access denied by account state
// ------------------------------------------------------------------

it('forbids banned admins from admin panel', function () {
    $user = makeAdminPanelUser('admin');
    $user->forceFill(['banned_until' => now()->addDay()])->save();

    actingAs($user->fresh())
        ->get('/admin')
        ->assertForbidden();
});

it('forbids deletion-requested admins from admin panel', function () {
    $user = makeAdminPanelUser('admin');
    $user->forceFill([
        'status' => UserStatus::DeletionRequested,
        'deletion_requested_at' => now(),
        'deletion_reason' => 'test reason',
    ])->save();

    actingAs($user->fresh())
        ->get('/admin')
        ->assertForbidden();
});
