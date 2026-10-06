<?php

declare(strict_types=1);

use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Models\AdminAuditLog;
use App\Models\User;
use Livewire\Livewire;

function makeAuditPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeAuditRecord(User $actor, array $overrides = []): AdminAuditLog
{
    return AdminAuditLog::create(array_merge([
        'user_id' => $actor->id,
        'action' => 'news.published',
        'subject_type' => 'App\\Models\\News',
        'subject_id' => 1,
        'created_at' => now(),
    ], $overrides));
}

// ------------------------------------------------------------------
// Access — admin-only
// ------------------------------------------------------------------

it('allows admins to access the activity log resource', function () {
    $admin = makeAuditPanelUser('admin');

    $this->actingAs($admin)->get('/admin/activity-logs')->assertOk();
});

it('denies editors access to the activity log resource', function () {
    $editor = makeAuditPanelUser('editor');

    $this->actingAs($editor)->get('/admin/activity-logs')->assertForbidden();
});

it('denies regular users access to the activity log resource', function () {
    $user = makeAuditPanelUser('user');

    $this->actingAs($user)->get('/admin/activity-logs')->assertForbidden();
});

// ------------------------------------------------------------------
// Read-only contract
// ------------------------------------------------------------------

it('is read-only and exposes no create, edit, delete or bulk actions', function () {
    $admin = makeAuditPanelUser('admin');
    makeAuditRecord($admin);

    Livewire::actingAs($admin)
        ->test(ListActivityLogs::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableActionDoesNotExist('export')
        ->assertTableBulkActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('export');

    expect(ActivityLogResource::canCreate())->toBeFalse()
        ->and(ActivityLogResource::canEdit(new AdminAuditLog))->toBeFalse()
        ->and(ActivityLogResource::canDelete(new AdminAuditLog))->toBeFalse()
        ->and(ActivityLogResource::canDeleteAny())->toBeFalse();
});

// ------------------------------------------------------------------
// Listing and filtering
// ------------------------------------------------------------------

it('lists audit entries and filters by action', function () {
    $admin = makeAuditPanelUser('admin');
    $published = makeAuditRecord($admin, ['action' => 'news.published']);
    $deleted = makeAuditRecord($admin, ['action' => 'news.deleted']);

    Livewire::actingAs($admin)
        ->test(ListActivityLogs::class)
        ->assertCanSeeTableRecords([$published, $deleted])
        ->filterTable('action', 'news.published')
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$deleted]);
});
