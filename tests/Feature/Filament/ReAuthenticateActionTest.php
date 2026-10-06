<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\AdminAuditLog;
use App\Models\Comment;
use App\Models\News;
use App\Models\User;
use Livewire\Livewire;

function makeReauthAdmin(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => bcrypt('reauth-admin-secret'),
    ]);
    $user->assignRole('admin');

    return $user;
}

it('performs the destructive action after a correct admin password', function () {
    $admin = makeReauthAdmin();
    $target = User::factory()->create();
    $target->assignRole('user');

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->mountTableAction('hard_delete', $target)
        ->set('mountedActions.0.data.password', 'reauth-admin-secret')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $deleted = User::withTrashed()->find($target->getKey());

    expect(User::query()->find($target->getKey()))->toBeNull()
        ->and($deleted)->not->toBeNull()
        ->and($deleted->trashed())->toBeTrue()
        ->and(AdminAuditLog::query()->where('action', 'user.hard_deleted')->count())->toBe(1);
});

it('blocks the destructive action when the password is wrong', function () {
    $admin = makeReauthAdmin();
    $target = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->mountTableAction('hard_delete', $target)
        ->set('mountedActions.0.data.password', 'not-the-password')
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['password']);

    expect(User::query()->find($target->getKey()))->not->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'user.hard_deleted')->count())->toBe(0);
});

it('blocks the destructive action when the password is missing', function () {
    $admin = makeReauthAdmin();
    $target = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->mountTableAction('hard_delete', $target)
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['password']);

    expect(User::query()->find($target->getKey()))->not->toBeNull();
});

it('does not expose the destructive action to non-admin managers', function () {
    $manager = User::factory()->create(['email_verified_at' => now()]);
    $manager->givePermissionTo('users.manage');
    $target = User::factory()->create();

    Livewire::actingAs($manager)
        ->test(ListUsers::class)
        ->assertTableActionHidden('hard_delete', $target);
});

it('keeps user content untouched when the account is hard deleted', function () {
    $admin = makeReauthAdmin();
    $target = User::factory()->create();
    $target->assignRole('user');

    $news = News::create([
        'user_id' => $target->getKey(),
        'scope' => News::SCOPE_PLAYER,
        'title' => 'Preserved after account deletion',
        'slug' => 'preserved-'.uniqid(),
        'body' => 'Content is not cascaded (R3).',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    $comment = Comment::create([
        'user_id' => $target->getKey(),
        'commentable_type' => News::class,
        'commentable_id' => $news->getKey(),
        'body' => 'A preserved comment body long enough.',
        'status' => Comment::STATUS_APPROVED,
    ]);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->mountTableAction('hard_delete', $target)
        ->set('mountedActions.0.data.password', 'reauth-admin-secret')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect(News::query()->find($news->getKey()))->not->toBeNull()
        ->and(Comment::query()->find($comment->getKey()))->not->toBeNull()
        ->and($news->fresh()->user_id)->toBe($target->getKey());
});

it('does not write PII into the hard delete audit entry', function () {
    $admin = makeReauthAdmin();
    $target = User::factory()->create(['email' => 'deleted-target@example.com']);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->mountTableAction('hard_delete', $target)
        ->set('mountedActions.0.data.password', 'reauth-admin-secret')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $log = AdminAuditLog::query()->where('action', 'user.hard_deleted')->first();

    expect($log)->not->toBeNull()
        ->and($log->old)->toBeNull()
        ->and($log->new)->toBeNull();
});
