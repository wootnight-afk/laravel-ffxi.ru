<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\AdminAuditLog;
use App\Models\Comment;
use App\Models\News;
use App\Models\User;
use Livewire\Livewire;

function makeRequestWorkflowAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

function makeRequestWorkflowEditor(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('editor');

    return $user;
}

it('restores an account that requested deletion', function () {
    $admin = makeRequestWorkflowAdmin();
    $user = User::factory()->create([
        'status' => UserStatus::DeletionRequested,
        'deletion_requested_at' => now()->subDay(),
        'deletion_reason' => 'Player wants to leave',
    ]);
    $user->assignRole('user');

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->callTableAction('restore', $user)
        ->assertHasNoTableActionErrors();

    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('records a restore in the audit trail', function () {
    $admin = makeRequestWorkflowAdmin();
    $user = User::factory()->create(['status' => UserStatus::Suspended]);
    $user->assignRole('user');

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->callTableAction('restore', $user)
        ->assertHasNoTableActionErrors();

    $log = AdminAuditLog::query()->where('action', 'user.restored')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_id)->toBe($user->getKey())
        ->and($log->user_id)->toBe($admin->getKey());
});

it('does not touch content when an account request is restored', function () {
    $admin = makeRequestWorkflowAdmin();
    $user = User::factory()->create(['status' => UserStatus::DeletionRequested]);
    $user->assignRole('user');

    $news = News::create([
        'user_id' => $user->getKey(),
        'scope' => News::SCOPE_PLAYER,
        'title' => 'Restored account content',
        'slug' => 'restored-'.uniqid(),
        'body' => 'Content survives restore (R3).',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    $comment = Comment::create([
        'user_id' => $user->getKey(),
        'commentable_type' => News::class,
        'commentable_id' => $news->getKey(),
        'body' => 'A surviving comment body long enough.',
        'status' => Comment::STATUS_APPROVED,
    ]);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->callTableAction('restore', $user)
        ->assertHasNoTableActionErrors();

    expect(News::query()->find($news->getKey()))->not->toBeNull()
        ->and(Comment::query()->find($comment->getKey()))->not->toBeNull()
        ->and($news->fresh()->deleted_at)->toBeNull();
});

it('hides the restore action for an active account', function () {
    $admin = makeRequestWorkflowAdmin();
    $user = User::factory()->create(['status' => UserStatus::Active]);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertTableActionHidden('restore', $user);
});

it('denies editors access to the account request surface', function () {
    $editor = makeRequestWorkflowEditor();

    $this->actingAs($editor)->get('/admin/users')->assertForbidden();
});

it('lets an admin hard delete an account from the request list without cascading', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'password' => bcrypt('request-admin-secret'),
    ]);
    $admin->assignRole('admin');

    $user = User::factory()->create([
        'status' => UserStatus::DeletionRequested,
        'deletion_requested_at' => now(),
    ]);
    $user->assignRole('user');

    $news = News::create([
        'user_id' => $user->getKey(),
        'scope' => News::SCOPE_PLAYER,
        'title' => 'Survives hard delete',
        'slug' => 'survives-'.uniqid(),
        'body' => 'Content is not cascaded (R3).',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->filterTable('account_requests')
        ->assertCanSeeTableRecords([$user])
        ->mountTableAction('hard_delete', $user)
        ->set('mountedActions.0.data.password', 'request-admin-secret')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect(User::query()->find($user->getKey()))->toBeNull()
        ->and(News::query()->find($news->getKey()))->not->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'user.hard_deleted')->count())->toBe(1);
});
