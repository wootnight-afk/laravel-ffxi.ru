<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Contracts\BackupStorage;
use Composer\InstalledVersions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Application-level backup orchestrator (ADR-004 R1, STAGE-9-CONTRACT 3.2).
 *
 * Knows nothing about the concrete storage backend: it works through
 * {@see BackupStorage}, so an external store can be added later without
 * touching this service.
 */
class BackupService
{
    /**
     * Extensions required by the production audit (spec section 7).
     *
     * @var array<int, string>
     */
    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql',
        'mbstring',
        'intl',
        'zip',
        'gd',
        'fileinfo',
        'exif',
        'curl',
        'openssl',
    ];

    public function __construct(
        private readonly DatabaseDumper $dumper,
        private readonly FilesArchiver $archiver,
        private readonly BackupStorage $storage,
        private readonly BackupLock $lock,
        private readonly BackupRetention $retention,
    ) {}

    /**
     * Create a backup for the given mode and scope.
     *
     * @param  string  $mode  full | site | site_only | db | db_only
     * @param  string|null  $scope  a | b | storage_app | whole_site | null
     * @param  string  $triggeredBy  cron | deploy | admin:{id} | cli
     * @param  int|null  $userId  actor id for admin-triggered backups
     */
    public function create(string $mode, ?string $scope, string $triggeredBy, ?int $userId = null): BackupManifest
    {
        $mode = $this->normaliseMode($mode);
        $scope = $this->normaliseScope($mode, $scope);
        $this->assertValidCombination($mode, $scope);

        if (! $this->lock->acquire('backup')) {
            throw new RuntimeException('Another backup or restore is already running.');
        }

        try {
            // Free-space safety (ADR-004 section 5.2): abort before writing
            // anything when the expected backup would leave too little space.
            $this->retention->ensureSpaceAvailable($this->expectedSizeBytes($mode, $scope));

            return $this->performCreate($mode, $scope, $triggeredBy, $userId);
        } finally {
            $this->lock->release('backup');
        }
    }

    public function find(string $backupId): ?BackupManifest
    {
        foreach ($this->storage->list() as $data) {
            if (($data['backup_id'] ?? null) === $backupId) {
                return BackupManifest::fromArray($data);
            }
        }

        return null;
    }

    /**
     * @return array<int, BackupManifest>
     */
    public function list(): array
    {
        return array_map(
            static fn (array $data): BackupManifest => BackupManifest::fromArray($data),
            $this->storage->list(),
        );
    }

    public function delete(string $backupId): void
    {
        $manifest = $this->find($backupId);

        if ($manifest === null) {
            return;
        }

        foreach ($manifest->artifacts as $relativePath) {
            if ($this->storage->exists($relativePath)) {
                $this->storage->delete($relativePath);
            }
        }

        $manifestPath = $this->manifestPath($manifest);

        if ($this->storage->exists($manifestPath)) {
            $this->storage->delete($manifestPath);
        }
    }

    private function performCreate(string $mode, ?string $scope, string $triggeredBy, ?int $userId): BackupManifest
    {
        $createdAt = Carbon::now()->utc();
        $backupId = (string) Str::uuid();
        $prefix = $createdAt->format('Y/m').'/'.$backupId;

        $workingDirectory = $this->workingDirectory();
        File::ensureDirectoryExists($workingDirectory);

        $artifacts = [];
        $files = [];
        $hashDb = null;
        $hashFiles = null;
        $sizeBytes = 0;

        if ($this->includesDatabase($mode)) {
            $dump = $this->dumper->dump($workingDirectory.'/'.$backupId.'.dump.gz');
            $hashDb = $dump['hash'];
            $sizeBytes += $this->fileSize($dump['path']);

            $relative = $prefix.'.dump.gz';
            $this->storage->put($relative, $dump['path']);
            $artifacts['db'] = $relative;
            @unlink($dump['path']);
        }

        if ($this->includesFiles($mode)) {
            $archive = $this->archiver->archive((string) $scope, $workingDirectory.'/'.$backupId.'.files.tar.gz');
            $hashFiles = $archive['hash'];
            $files = $archive['files'];
            $sizeBytes += $this->fileSize($archive['path']);

            $relative = $prefix.'.files.tar.gz';
            $this->storage->put($relative, $archive['path']);
            $artifacts['files'] = $relative;
            @unlink($archive['path']);
        }

        $manifest = new BackupManifest(
            formatVersion: (int) config('backup.manifest_version', 1),
            backupId: $backupId,
            type: $mode,
            scope: $scope,
            isComplete: $mode !== BackupManifest::TYPE_DB_ONLY,
            createdAt: $createdAt->toIso8601String(),
            triggeredBy: $triggeredBy,
            appVersion: $this->resolveAppVersion(),
            appChangelogHash: $this->fileHash(base_path('CHANGELOG.md')),
            phpVersion: PHP_VERSION,
            laravelVersion: $this->packageVersion('laravel/framework'),
            filamentVersion: $this->packageVersion('filament/filament'),
            livewireVersion: $this->packageVersion('livewire/livewire'),
            composerLockHash: $this->fileHash(base_path('composer.lock')),
            packageLockHash: $this->fileHash(base_path('package-lock.json')),
            dbSchemaVersion: $this->dbSchemaVersion(),
            dbMigrations: $this->dbMigrations(),
            extensions: $this->extensions(),
            dbSizeBytes: $this->dbSizeBytes(),
            files: $files,
            hashDb: $hashDb,
            hashFiles: $hashFiles,
            createdByUserId: $userId,
            sizeBytes: $sizeBytes,
            artifacts: $artifacts,
        );

        $manifest->validate();

        $manifestRelative = $prefix.'.manifest.json';
        $temporaryManifest = $workingDirectory.'/'.$backupId.'.manifest.json';
        file_put_contents($temporaryManifest, $manifest->toJson());
        $this->storage->put($manifestRelative, $temporaryManifest);
        @unlink($temporaryManifest);

        return $manifest;
    }

    private function normaliseMode(string $mode): string
    {
        return match ($mode) {
            'full' => BackupManifest::TYPE_FULL,
            'site', 'site_only' => BackupManifest::TYPE_SITE_ONLY,
            'db', 'db_only' => BackupManifest::TYPE_DB_ONLY,
            default => throw new RuntimeException("Unknown backup mode [{$mode}]."),
        };
    }

    private function normaliseScope(string $mode, ?string $scope): ?string
    {
        if ($mode === BackupManifest::TYPE_DB_ONLY) {
            return null;
        }

        return match ($scope) {
            null, '' => null,
            'a', 'storage_app' => BackupManifest::SCOPE_STORAGE_APP,
            'b', 'whole_site' => BackupManifest::SCOPE_WHOLE_SITE,
            default => throw new RuntimeException("Unknown backup scope [{$scope}]."),
        };
    }

    private function assertValidCombination(string $mode, ?string $scope): void
    {
        if ($mode === BackupManifest::TYPE_DB_ONLY) {
            return;
        }

        if ($scope === null) {
            throw new RuntimeException("A {$mode} backup requires an explicit scope.");
        }
    }

    private function includesDatabase(string $mode): bool
    {
        return $mode === BackupManifest::TYPE_FULL || $mode === BackupManifest::TYPE_DB_ONLY;
    }

    private function includesFiles(string $mode): bool
    {
        return $mode === BackupManifest::TYPE_FULL || $mode === BackupManifest::TYPE_SITE_ONLY;
    }

    /**
     * Estimated uncompressed size of the artifacts a backup will produce,
     * used by the free-space safety check (ADR-004 section 5.2).
     */
    private function expectedSizeBytes(string $mode, ?string $scope): int
    {
        $expected = 0;

        if ($this->includesDatabase($mode)) {
            $expected += $this->dbSizeBytes();
        }

        if ($this->includesFiles($mode)) {
            $expected += $this->archiver->estimateSize((string) $scope);
        }

        return $expected;
    }

    private function workingDirectory(): string
    {
        return storage_path('framework/backup-tmp');
    }

    private function fileSize(string $path): int
    {
        $size = @filesize($path);

        return $size === false ? 0 : $size;
    }

    private function fileHash(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $hash = hash_file('sha256', $path);

        return $hash === false ? '' : $hash;
    }

    private function resolveAppVersion(): ?string
    {
        try {
            $result = Process::path(base_path())->run(['git', 'describe', '--tags', '--always']);

            if ($result->successful()) {
                $output = trim($result->output());

                return $output === '' ? null : $output;
            }
        } catch (Throwable) {
            // git is unavailable (for example on production without .git).
        }

        return null;
    }

    private function packageVersion(string $package): string
    {
        if (class_exists(InstalledVersions::class)) {
            try {
                $version = InstalledVersions::getPrettyVersion($package);

                if ($version !== null) {
                    return $version;
                }
            } catch (Throwable) {
                // Fall through to the lock file.
            }
        }

        return $this->versionFromLock($package);
    }

    private function versionFromLock(string $package): string
    {
        static $lock = null;

        if ($lock === null) {
            $path = base_path('composer.lock');
            $lock = is_file($path)
                ? (array) json_decode((string) file_get_contents($path), true)
                : [];
        }

        foreach (['packages', 'packages-dev'] as $section) {
            /** @var array<int, array<string, mixed>> $packages */
            $packages = (array) ($lock[$section] ?? []);

            foreach ($packages as $entry) {
                if (($entry['name'] ?? null) === $package) {
                    return (string) ($entry['version'] ?? '');
                }
            }
        }

        return '';
    }

    private function dbSchemaVersion(): string
    {
        $migration = DB::table('migrations')->orderByDesc('id')->value('migration');

        return $migration === null ? '' : (string) $migration;
    }

    /**
     * @return array<int, string>
     */
    private function dbMigrations(): array
    {
        return DB::table('migrations')
            ->orderBy('id')
            ->pluck('migration')
            ->map(static fn (mixed $migration): string => (string) $migration)
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function extensions(): array
    {
        $loaded = array_map('strtolower', get_loaded_extensions());
        $extensions = [];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $extensions[$extension] = in_array($extension, $loaded, true);
        }

        return $extensions;
    }

    private function dbSizeBytes(): int
    {
        $row = DB::selectOne(
            'SELECT COALESCE(SUM(data_length + index_length), 0) AS size
             FROM information_schema.tables
             WHERE table_schema = ?',
            [DB::getDatabaseName()],
        );

        if ($row === null) {
            return 0;
        }

        /** @var array<string, mixed> $row */
        $row = (array) $row;

        return (int) ($row['size'] ?? 0);
    }

    /**
     * Relative path of a manifest inside the backup store (contract section 5.2).
     */
    public function manifestPath(BackupManifest $manifest): string
    {
        $createdAt = Carbon::parse($manifest->createdAt)->utc();

        return $createdAt->format('Y/m').'/'.$manifest->backupId.'.manifest.json';
    }
}
