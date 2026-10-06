<?php

use App\Contracts\BackupStorage;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\LocalBackupStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backupDir = sys_get_temp_dir().'/ffxi-cleanup-'.bin2hex(random_bytes(6));
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
    Carbon::setTestNow();
    File::deleteDirectory($this->backupDir);
    File::deleteDirectory(storage_path('framework/backup-tmp'));
});

/**
 * @param  array<int, string>  $moments
 * @return array<int, string>
 */
function seedBackupsAt(array $moments): array
{
    $service = app(BackupService::class);
    $ids = [];

    foreach ($moments as $moment) {
        Carbon::setTestNow(Carbon::parse($moment, 'UTC'));
        $ids[] = $service->create('db_only', null, 'cli')->backupId;
    }

    return $ids;
}

it('deletes expired backups and keeps the protected ones', function () {
    $ids = seedBackupsAt([
        '2026-10-06 10:00:00',
        '2026-10-05 10:00:00',
        '2026-09-20 10:00:00',
        '2026-01-15 10:00:00',
        '2025-01-15 10:00:00',
        '2025-06-01 10:00:00',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'UTC'));

    $this->artisan('app:backup-cleanup')
        ->expectsOutputToContain('Deleted 2 expired backup(s).')
        ->assertExitCode(0);

    $service = app(BackupService::class);

    expect($service->find($ids[0]))->not->toBeNull();
    expect($service->find($ids[1]))->not->toBeNull();
    expect($service->find($ids[2]))->not->toBeNull();
    expect($service->find($ids[3]))->not->toBeNull();
    expect($service->find($ids[4]))->toBeNull();
    expect($service->find($ids[5]))->toBeNull();

    expect($this->storage->list())->toHaveCount(4);
});

it('reports zero deletions when everything is protected', function () {
    seedBackupsAt(['2026-10-06 10:00:00', '2026-10-05 09:00:00']);

    Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'UTC'));

    $this->artisan('app:backup-cleanup')
        ->expectsOutputToContain('Deleted 0 expired backup(s).')
        ->assertExitCode(0);

    expect($this->storage->list())->toHaveCount(2);
});

it('refuses to clean up while another backup or restore holds the lock', function () {
    $holder = new BackupLock;
    expect($holder->acquire('backup'))->toBeTrue();

    // A distinct instance cannot take the lock while the holder owns it.
    $this->app->instance(BackupLock::class, new BackupLock);

    $this->artisan('app:backup-cleanup')->assertExitCode(1);

    $holder->release('backup');
});
