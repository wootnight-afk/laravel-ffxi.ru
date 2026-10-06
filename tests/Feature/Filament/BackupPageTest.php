<?php

declare(strict_types=1);

use App\Contracts\BackupStorage;
use App\Filament\Pages\BackupPage;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\LocalBackupStorage;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->backupDir = sys_get_temp_dir().'/ffxi-backup-page-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->backupDir);
    config([
        'backup.path' => $this->backupDir,
        'backup.lock_path' => $this->backupDir.'/backup.lock',
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
    File::deleteDirectory($this->backupDir);
    File::deleteDirectory(storage_path('framework/backup-tmp'));
});

function backupPageAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

function backupPageRole(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeDbBackup(): string
{
    return app(BackupService::class)->create('db_only', null, 'cli')->backupId;
}

// ------------------------------------------------------------------
// Authorization
// ------------------------------------------------------------------

it('allows admins to open the backup page', function () {
    $admin = backupPageAdmin();

    $this->actingAs($admin)
        ->get('/admin/backups')
        ->assertOk()
        ->assertSee('Резервные копии');
});

it('denies editors access to the backup page', function () {
    $this->actingAs(backupPageRole('editor'))
        ->get('/admin/backups')
        ->assertForbidden();
});

it('denies regular users access to the backup page', function () {
    $this->actingAs(backupPageRole('user'))
        ->get('/admin/backups')
        ->assertForbidden();
});

it('reports canAccess false for guest, user and editor, and true for admin', function () {
    expect(BackupPage::canAccess())->toBeFalse();

    $this->actingAs(backupPageRole('user'));
    expect(BackupPage::canAccess())->toBeFalse();

    $this->actingAs(backupPageRole('editor'));
    expect(BackupPage::canAccess())->toBeFalse();

    $this->actingAs(backupPageAdmin());
    expect(BackupPage::canAccess())->toBeTrue();
});

// ------------------------------------------------------------------
// Listing
// ------------------------------------------------------------------

it('lists existing backups in the table', function () {
    $admin = backupPageAdmin();
    $backupId = makeDbBackup();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->assertOk()
        ->assertSee($backupId);
});

// ------------------------------------------------------------------
// Create
// ------------------------------------------------------------------

it('creates a backup from the page and records an audit entry without IP', function () {
    $admin = backupPageAdmin();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->mountTableAction('createBackup')
        ->set('mountedActions.0.data.mode', 'db_only')
        ->callMountedTableAction()
        ->assertHasNoErrors();

    expect($this->storage->list())->toHaveCount(1);

    $log = AdminAuditLog::query()->where('action', 'backup.created')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->getKey())
        ->and($log->ip)->toBeNull()
        ->and($log->new['type'])->toBe('db_only');
});

it('does not expose backup management to editors', function () {
    $editor = backupPageRole('editor');

    $this->actingAs($editor)->get('/admin/backups')->assertForbidden();

    expect(BackupPage::canAccess())->toBeFalse();
    expect($this->storage->list())->toBe([]);
});

it('decides the Scope B warning from mode and scope', function () {
    expect(BackupPage::scopeRequiresWarning('full', 'whole_site'))->toBeTrue()
        ->and(BackupPage::scopeRequiresWarning('site_only', 'whole_site'))->toBeTrue()
        ->and(BackupPage::scopeRequiresWarning('full', 'storage_app'))->toBeFalse()
        ->and(BackupPage::scopeRequiresWarning('site_only', 'storage_app'))->toBeFalse()
        ->and(BackupPage::scopeRequiresWarning('db_only', null))->toBeFalse();
});

it('offers a create-backup action that opens a form', function () {
    $admin = backupPageAdmin();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->mountTableAction('createBackup')
        ->assertTableActionDataSet(['mode' => 'full', 'scope' => 'storage_app']);
});

// ------------------------------------------------------------------
// Manifest, download, delete
// ------------------------------------------------------------------

it('exposes the manifest of a backup', function () {
    $admin = backupPageAdmin();
    $backupId = makeDbBackup();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->mountTableAction('viewManifest', $backupId)
        ->assertSee($backupId);
});

it('downloads the manifest as json', function () {
    $admin = backupPageAdmin();
    $backupId = makeDbBackup();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->callTableAction('downloadManifest', $backupId)
        ->assertFileDownloaded($backupId.'.manifest.json');
});

it('deletes a backup from the page and records an audit entry', function () {
    $admin = backupPageAdmin();
    $backupId = makeDbBackup();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->callTableAction('deleteBackup', $backupId)
        ->assertHasNoErrors();

    expect($this->storage->list())->toBe([]);

    $log = AdminAuditLog::query()->where('action', 'backup.deleted')->first();

    expect($log)->not->toBeNull()
        ->and($log->new['backup_id'])->toBe($backupId)
        ->and($log->ip)->toBeNull();
});

// ------------------------------------------------------------------
// Retention settings
// ------------------------------------------------------------------

it('saves the retention settings from the page', function () {
    $admin = backupPageAdmin();

    Livewire::actingAs($admin)
        ->test(BackupPage::class)
        ->mountTableAction('retention')
        ->set('mountedActions.0.data.backup_retention_daily', 3)
        ->set('mountedActions.0.data.backup_retention_weekly', 2)
        ->set('mountedActions.0.data.backup_retention_monthly', 6)
        ->set('mountedActions.0.data.min_free_space_pct', 10)
        ->callMountedTableAction()
        ->assertHasNoErrors();

    $settings = app(SettingsRepository::class);

    expect($settings->int('backup_retention_daily'))->toBe(3)
        ->and($settings->int('backup_retention_weekly'))->toBe(2)
        ->and($settings->int('backup_retention_monthly'))->toBe(6)
        ->and($settings->int('min_free_space_pct'))->toBe(10);
});
