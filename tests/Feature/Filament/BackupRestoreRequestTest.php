<?php

declare(strict_types=1);

use App\Contracts\BackupStorage;
use App\Filament\Pages\BackupPage;
use App\Models\AdminAuditLog;
use App\Models\RestoreRequest;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\LocalBackupStorage;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/ffxi-e95-page-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->dir);
    config([
        'backup.path' => $this->dir,
        'backup.lock_path' => $this->dir.'/backup.lock',
    ]);

    $this->storage = new LocalBackupStorage;
    $this->app->instance(BackupStorage::class, $this->storage);

    $this->app->instance(DatabaseDumper::class, new class extends DatabaseDumper
    {
        public function dump(string $outputPath): array
        {
            file_put_contents($outputPath, gzencode('-- stub dump'));

            return ['path' => $outputPath, 'hash' => hash_file('sha256', $outputPath)];
        }

        public function available(): bool
        {
            return true;
        }
    });
});

afterEach(function () {
    File::deleteDirectory($this->dir);
    File::deleteDirectory(storage_path('framework/backup-tmp'));
});

function restorePageAdmin(string $password = 'page-secret'): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => bcrypt($password),
    ]);
    $user->assignRole('admin');

    return $user;
}

function restorePageRole(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function restorePageBackup(): string
{
    return app(BackupService::class)->create('db_only', null, 'cli')->backupId;
}

it('creates a restore request from the page and records a security audit entry with IP', function () {
    $admin = restorePageAdmin();
    $backupId = restorePageBackup();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->mountTableAction('restoreRequest', $backupId)
        ->set('mountedActions.0.data.password', 'page-secret')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $request = RestoreRequest::query()->first();

    expect($request)->not->toBeNull()
        ->and($request->backup_id)->toBe($backupId)
        ->and($request->admin_user_id)->toBe($admin->getKey())
        ->and($request->status)->toBe(RestoreRequest::STATUS_PENDING);

    $log = AdminAuditLog::query()->where('action', 'restore.requested')->first();

    expect($log)->not->toBeNull()
        ->and($log->ip)->not->toBeNull()
        ->and($log->new['request_id'])->toBe($request->id)
        ->and($log->new['backup_id'])->toBe($backupId);
});

it('blocks the restore request when the password is wrong', function () {
    $admin = restorePageAdmin();
    $backupId = restorePageBackup();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->mountTableAction('restoreRequest', $backupId)
        ->set('mountedActions.0.data.password', 'wrong-password')
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['password']);

    expect(RestoreRequest::query()->count())->toBe(0)
        ->and(AdminAuditLog::query()->where('action', 'restore.requested')->exists())->toBeFalse();
});

it('blocks the restore request when the password is missing', function () {
    $admin = restorePageAdmin();
    $backupId = restorePageBackup();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->mountTableAction('restoreRequest', $backupId)
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['password']);

    expect(RestoreRequest::query()->count())->toBe(0);
});

it('does not let editors or regular users request a restore', function () {
    $backupId = restorePageBackup();

    foreach (['editor', 'user'] as $role) {
        $this->actingAs(restorePageRole($role))
            ->get('/admin/backups')
            ->assertForbidden();
    }

    expect(BackupPage::canAccess())->toBeFalse()
        ->and(RestoreRequest::query()->count())->toBe(0);
});
