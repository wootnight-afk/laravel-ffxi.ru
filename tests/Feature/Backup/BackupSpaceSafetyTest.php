<?php

use App\Contracts\BackupStorage;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\LocalBackupStorage;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backupDir = sys_get_temp_dir().'/ffxi-safety-'.bin2hex(random_bytes(6));
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

it('aborts creation without artifacts when free space is insufficient', function () {
    app(SettingsRepository::class)->set('min_free_space_pct', 100);

    $service = app(BackupService::class);

    expect(fn () => $service->create('db_only', null, 'cli'))
        ->toThrow(RuntimeException::class, 'Not enough free disk space');

    expect($this->storage->list())->toBe([]);
});

it('creates the backup when free space is sufficient', function () {
    app(SettingsRepository::class)->set('min_free_space_pct', 0);

    $manifest = app(BackupService::class)->create('db_only', null, 'cli');

    expect($manifest->backupId)->not->toBe('');
    expect($this->storage->list())->toHaveCount(1);
});
