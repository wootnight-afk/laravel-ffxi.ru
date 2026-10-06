<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\UserRank;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function makeRoleAuditAdmin(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => bcrypt('role-audit-secret'),
    ]);
    $user->assignRole('admin');

    return $user;
}

function makeRoleAuditTarget(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now()->startOfMinute(),
    ], $overrides));
    $user->assignRole('user');

    return $user;
}

it('records a dedicated audit entry when an admin changes a user role', function () {
    $admin = makeRoleAuditAdmin();
    $target = makeRoleAuditTarget();
    $editorId = Role::findByName('editor', 'web')->getKey();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.roles', [$editorId])
        ->call('save')
        ->assertHasNoErrors();

    $log = AdminAuditLog::query()->where('action', 'user.roles_changed')->first();

    expect($target->refresh()->getRoleNames()->all())->toBe(['editor'])
        ->and($log)->not->toBeNull()
        ->and($log->subject_type)->toBe(User::class)
        ->and($log->subject_id)->toBe($target->getKey())
        ->and($log->user_id)->toBe($admin->getKey())
        ->and($log->old)->toBe(['roles' => ['user']])
        ->and($log->new)->toBe(['roles' => ['editor']]);
});

it('writes exactly one roles_changed entry per role change', function () {
    $admin = makeRoleAuditAdmin();
    $target = makeRoleAuditTarget();
    $editorId = Role::findByName('editor', 'web')->getKey();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.roles', [$editorId])
        ->call('save')
        ->assertHasNoErrors();

    expect(AdminAuditLog::query()->where('action', 'user.roles_changed')->count())->toBe(1)
        ->and(AdminAuditLog::query()->count())->toBe(1);
});

it('does not write a roles_changed entry when the roles are unchanged', function () {
    $admin = makeRoleAuditAdmin();
    $target = makeRoleAuditTarget();
    $userId = Role::findByName('user', 'web')->getKey();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.roles', [$userId])
        ->call('save')
        ->assertHasNoErrors();

    expect(AdminAuditLog::query()->where('action', 'user.roles_changed')->count())->toBe(0)
        ->and($target->refresh()->getRoleNames()->all())->toBe(['user']);
});

it('keeps the roles_changed payload free of pii and secrets', function () {
    $admin = makeRoleAuditAdmin();
    $target = makeRoleAuditTarget(['email' => 'role-target@example.com']);
    $editorId = Role::findByName('editor', 'web')->getKey();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.roles', [$editorId])
        ->call('save')
        ->assertHasNoErrors();

    $log = AdminAuditLog::query()->where('action', 'user.roles_changed')->firstOrFail();
    $payload = (string) json_encode([$log->old, $log->new]);

    expect($log->old)->toBe(['roles' => ['user']])
        ->and($log->new)->toBe(['roles' => ['editor']])
        ->and($payload)->not->toContain('role-target@example.com')
        ->and($payload)->not->toContain('password')
        ->and($payload)->not->toContain('role-audit-secret');
});

it('still records ordinary user updates alongside role changes', function () {
    $admin = makeRoleAuditAdmin();
    $target = makeRoleAuditTarget();
    $editorId = Role::findByName('editor', 'web')->getKey();
    $rank = UserRank::create(['key' => 'role-audit-rank', 'title' => 'Role audit rank']);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.rank_id', $rank->getKey())
        ->set('data.roles', [$editorId])
        ->call('save')
        ->assertHasNoErrors();

    $roleLog = AdminAuditLog::query()->where('action', 'user.roles_changed')->firstOrFail();
    $updateLog = AdminAuditLog::query()->where('action', 'user.updated')->firstOrFail();

    expect(AdminAuditLog::query()->where('action', 'user.roles_changed')->count())->toBe(1)
        ->and(AdminAuditLog::query()->where('action', 'user.updated')->count())->toBe(1)
        ->and($roleLog->new)->toBe(['roles' => ['editor']])
        ->and($updateLog->new)->toBe(['rank_id' => $rank->getKey()])
        ->and($target->refresh()->rank_id)->toBe($rank->getKey());
});
