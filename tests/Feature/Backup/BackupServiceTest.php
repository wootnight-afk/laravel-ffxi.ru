<?php

use App\Contracts\BackupStorage;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\LocalBackupStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->storageDir = sys_get_temp_dir().'/ffxi-backup-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->storageDir);
    config([
        'backup.path' => $this->storageDir,
        'backup.lock_path' => $this->storageDir.'/backup.lock',
    ]);

    $this->storage = new LocalBackupStorage;
    $this->app->instance(BackupStorage::class, $this->storage);

    $this->app->instance(DatabaseDumper::class, new class extends DatabaseDumper
    {
        public function dump(string $outputPath): array
        {
            file_put_contents($outputPath, gzencode('-- fake dump'));

            return ['path' => $outputPath, 'hash' => hash_file('sha256', $outputPath)];
        }

        public function available(): bool
        {
            return true;
        }
    });

    $this->service = $this->app->make(BackupService::class);
});

afterEach(function () {
    File::deleteDirectory($this->storageDir);
    File::deleteDirectory(storage_path('framework/backup-tmp'));
});

it('creates a full backup with storage_app scope', function () {
    $manifest = $this->service->create('full', 'storage_app', 'cli');

    expect($manifest->type)->toBe('full');
    expect($manifest->scope)->toBe('storage_app');
    expect($manifest->isComplete)->toBeTrue();
    expect($manifest->hashDb)->not->toBeNull();
    expect($manifest->hashFiles)->not->toBeNull();
    expect($manifest->files)->toBe(['storage/app']);
    expect($manifest->formatVersion)->toBe(1);
    expect($manifest->phpVersion)->toBe(PHP_VERSION);
    expect($manifest->laravelVersion)->not->toBe('');
    expect($manifest->artifacts)->toHaveKeys(['db', 'files']);
    expect($manifest->sizeBytes)->toBeGreaterThan(0);

    foreach ($manifest->artifacts as $relativePath) {
        expect($this->storage->exists($relativePath))->toBeTrue();
    }

    $prefix = Carbon::parse($manifest->createdAt)->utc()->format('Y/m');
    expect($this->storage->exists($prefix.'/'.$manifest->backupId.'.manifest.json'))->toBeTrue();
});

it('creates a full backup with whole_site scope', function () {
    $manifest = $this->service->create('full', 'whole_site', 'cli');

    expect($manifest->scope)->toBe('whole_site');
    expect($manifest->isComplete)->toBeTrue();
    expect($manifest->hashDb)->not->toBeNull();
    expect($manifest->hashFiles)->not->toBeNull();
    expect($manifest->files)->toContain('app', 'config', 'storage/app');
});

it('creates a site_only backup without a database hash', function () {
    $manifest = $this->service->create('site_only', 'storage_app', 'deploy');

    expect($manifest->type)->toBe('site_only');
    expect($manifest->scope)->toBe('storage_app');
    expect($manifest->isComplete)->toBeTrue();
    expect($manifest->hashDb)->toBeNull();
    expect($manifest->hashFiles)->not->toBeNull();
    expect($manifest->artifacts)->toHaveKey('files');
    expect($manifest->artifacts)->not->toHaveKey('db');
});

it('creates a site_only whole_site backup without a database hash', function () {
    $manifest = $this->service->create('site_only', 'whole_site', 'cli');

    expect($manifest->scope)->toBe('whole_site');
    expect($manifest->hashDb)->toBeNull();
    expect($manifest->hashFiles)->not->toBeNull();
});

it('creates a db_only backup that is incomplete and fileless', function () {
    $manifest = $this->service->create('db_only', null, 'cron');

    expect($manifest->type)->toBe('db_only');
    expect($manifest->scope)->toBeNull();
    expect($manifest->isComplete)->toBeFalse();
    expect($manifest->hashDb)->not->toBeNull();
    expect($manifest->hashFiles)->toBeNull();
    expect($manifest->files)->toBe([]);
    expect($manifest->artifacts)->toHaveKey('db');
    expect($manifest->artifacts)->not->toHaveKey('files');
});

it('normalises the CLI aliases for mode and scope', function () {
    $manifest = $this->service->create('site', 'b', 'cli');

    expect($manifest->type)->toBe('site_only');
    expect($manifest->scope)->toBe('whole_site');
});

it('rejects a file mode without a scope', function () {
    expect(fn () => $this->service->create('full', null, 'cli'))
        ->toThrow(RuntimeException::class, 'requires an explicit scope');

    expect(fn () => $this->service->create('site_only', null, 'cli'))
        ->toThrow(RuntimeException::class, 'requires an explicit scope');
});

it('rejects an unknown mode', function () {
    expect(fn () => $this->service->create('incremental', 'storage_app', 'cli'))
        ->toThrow(RuntimeException::class, 'Unknown backup mode');
});

it('finds, lists and deletes a backup', function () {
    $created = $this->service->create('db_only', null, 'cli');

    $found = $this->service->find($created->backupId);
    expect($found)->not->toBeNull();
    expect($found->backupId)->toBe($created->backupId);

    expect($this->service->list())->toHaveCount(1);

    $this->service->delete($created->backupId);

    expect($this->service->find($created->backupId))->toBeNull();
    expect($this->service->list())->toBe([]);
    expect($this->storage->exists($created->artifacts['db']))->toBeFalse();
});

it('does nothing when deleting an unknown backup', function () {
    $this->service->delete('00000000-0000-0000-0000-000000000000');

    expect($this->service->list())->toBe([]);
});

it('refuses to start when the backup lock is already held', function () {
    $holder = new BackupLock;
    expect($holder->acquire('backup'))->toBeTrue();

    // A distinct instance cannot take the lock while the holder owns it.
    $this->app->instance(BackupLock::class, new BackupLock);

    $service = $this->app->make(BackupService::class);

    expect(fn () => $service->create('db_only', null, 'cli'))
        ->toThrow(RuntimeException::class, 'already running');

    $holder->release('backup');
});
