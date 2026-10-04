<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\GuestVisitor;
use App\Models\News;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function makeManagementPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('allows admins to open the user, role, and guest resources', function () {
    $admin = makeManagementPanelUser('admin');

    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/roles')->assertOk();
    $this->actingAs($admin)->get('/admin/guests')->assertOk();
});

it('denies editors access to user, role, and guest resources', function () {
    $editor = makeManagementPanelUser('editor');

    $this->actingAs($editor)->get('/admin/users')->assertForbidden();
    $this->actingAs($editor)->get('/admin/roles')->assertForbidden();
    $this->actingAs($editor)->get('/admin/guests')->assertForbidden();
});

it('denies editors direct access to a user edit page', function () {
    $editor = makeManagementPanelUser('editor');
    $target = makeManagementPanelUser('user');

    $this->actingAs($editor)
        ->get("/admin/users/{$target->getKey()}/edit")
        ->assertForbidden();
});

it('prevents admins from demoting themselves through role assignment', function () {
    $admin = makeManagementPanelUser('admin');

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $admin->getKey()])
        ->assertFormFieldDisabled('roles');
});

it('filters the user list to deletion and suspension requests', function () {
    $admin = makeManagementPanelUser('admin');
    $deletionRequest = User::factory()->create([
        'status' => UserStatus::DeletionRequested,
        'deletion_requested_at' => now(),
        'deletion_reason' => 'Deletion request',
    ]);
    $suspensionRequest = User::factory()->create([
        'status' => UserStatus::Suspended,
        'suspended_at' => now(),
        'suspension_reason' => 'Suspension request',
    ]);
    $activeUser = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->filterTable('account_requests')
        ->assertCanSeeTableRecords([$deletionRequest, $suspensionRequest])
        ->assertCanNotSeeTableRecords([$activeUser]);
});

it('restores only the account status without changing request history or content', function () {
    $admin = makeManagementPanelUser('admin');
    $user = User::factory()->create([
        'status' => UserStatus::Suspended,
        'suspended_at' => now()->subDay(),
        'suspension_reason' => 'Keep this history',
    ]);
    $user->assignRole('user');
    $content = News::query()->create([
        'user_id' => $user->getKey(),
        'scope' => News::SCOPE_PLAYER,
        'title' => 'Preserved user content',
        'body' => 'The account status does not delete this.',
        'status' => News::STATUS_PUBLISHED,
    ]);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->callTableAction('restore', $user)
        ->assertHasNoTableActionErrors();

    $user->refresh();

    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->suspended_at)->not->toBeNull()
        ->and($user->suspension_reason)->toBe('Keep this history')
        ->and($content->fresh())->not->toBeNull();
});

it('does not offer a hard-delete action before the re-authenticated E8 flow', function () {
    $admin = makeManagementPanelUser('admin');
    $user = makeManagementPanelUser('user');

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertTableActionDoesNotExist('delete');
});

it('keeps the role catalog read-only and limited to the seeded roles', function () {
    $admin = makeManagementPanelUser('admin');

    expect(RoleResource::getPages())->toHaveKey('index')
        ->and(RoleResource::canCreate())->toBeFalse()
        ->and(Role::query()->whereIn('name', ['admin', 'editor', 'user'])->count())->toBe(3);

    $this->actingAs($admin)->get('/admin/roles')->assertOk();
});

it('shows guest names but does not expose guest identifiers or ip hashes', function () {
    $admin = makeManagementPanelUser('admin');
    $guest = GuestVisitor::query()->create([
        'uuid' => '4aaae422-3f02-49c7-9b9a-98764c24f5b2',
        'display_name' => 'guestSafe',
        'ip_hash' => str_repeat('f', 64),
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now(),
        'hits' => 4,
    ]);

    $this->actingAs($admin)
        ->get('/admin/guests')
        ->assertOk()
        ->assertSee('guestSafe')
        ->assertDontSee($guest->uuid)
        ->assertDontSee($guest->ip_hash);
});
