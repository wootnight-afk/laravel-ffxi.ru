<?php

declare(strict_types=1);

namespace App\Services\Backup;

use InvalidArgumentException;

/**
 * Immutable description of a single backup (ADR-004 section 4).
 *
 * Carries only serialization and structural validation. It never touches the
 * filesystem, the database or the container, so it can be built and asserted
 * in isolation.
 */
final readonly class BackupManifest
{
    public const TYPE_FULL = 'full';

    public const TYPE_SITE_ONLY = 'site_only';

    public const TYPE_DB_ONLY = 'db_only';

    /** @var array<int, string> */
    public const TYPES = [self::TYPE_FULL, self::TYPE_SITE_ONLY, self::TYPE_DB_ONLY];

    public const SCOPE_STORAGE_APP = 'storage_app';

    public const SCOPE_WHOLE_SITE = 'whole_site';

    /** @var array<int, string> */
    public const SCOPES = [self::SCOPE_STORAGE_APP, self::SCOPE_WHOLE_SITE];

    /**
     * @param  array<int, string>  $dbMigrations
     * @param  array<string, bool>  $extensions
     * @param  array<int, string>  $files
     * @param  array<string, string>  $artifacts
     */
    public function __construct(
        public int $formatVersion,
        public string $backupId,
        public string $type,
        public ?string $scope,
        public bool $isComplete,
        public string $createdAt,
        public string $triggeredBy,
        public ?string $appVersion,
        public string $appChangelogHash,
        public string $phpVersion,
        public string $laravelVersion,
        public string $filamentVersion,
        public string $livewireVersion,
        public string $composerLockHash,
        public string $packageLockHash,
        public string $dbSchemaVersion,
        public array $dbMigrations,
        public array $extensions,
        public int $dbSizeBytes,
        public array $files,
        public ?string $hashDb,
        public ?string $hashFiles,
        public ?int $createdByUserId,
        public int $sizeBytes,
        public array $artifacts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'format_version' => $this->formatVersion,
            'backup_id' => $this->backupId,
            'type' => $this->type,
            'scope' => $this->scope,
            'is_complete' => $this->isComplete,
            'created_at' => $this->createdAt,
            'triggered_by' => $this->triggeredBy,
            'app_version' => $this->appVersion,
            'app_changelog_hash' => $this->appChangelogHash,
            'php_version' => $this->phpVersion,
            'laravel_version' => $this->laravelVersion,
            'filament_version' => $this->filamentVersion,
            'livewire_version' => $this->livewireVersion,
            'composer_lock_hash' => $this->composerLockHash,
            'package_lock_hash' => $this->packageLockHash,
            'db_schema_version' => $this->dbSchemaVersion,
            'db_migrations' => $this->dbMigrations,
            'extensions' => $this->extensions,
            'db_size_bytes' => $this->dbSizeBytes,
            'files' => $this->files,
            'hash_db' => $this->hashDb,
            'hash_files' => $this->hashFiles,
            'created_by_user_id' => $this->createdByUserId,
            'size_bytes' => $this->sizeBytes,
            'artifacts' => $this->artifacts,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, string> $dbMigrations */
        $dbMigrations = array_values(array_map('strval', (array) ($data['db_migrations'] ?? [])));

        /** @var array<string, bool> $extensions */
        $extensions = array_map(
            static fn (mixed $value): bool => (bool) $value,
            (array) ($data['extensions'] ?? []),
        );

        /** @var array<int, string> $files */
        $files = array_values(array_map('strval', (array) ($data['files'] ?? [])));

        /** @var array<string, string> $artifacts */
        $artifacts = array_map(
            static fn (mixed $value): string => (string) $value,
            (array) ($data['artifacts'] ?? []),
        );

        return new self(
            formatVersion: (int) ($data['format_version'] ?? 1),
            backupId: (string) ($data['backup_id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            scope: isset($data['scope']) ? (string) $data['scope'] : null,
            isComplete: (bool) ($data['is_complete'] ?? false),
            createdAt: (string) ($data['created_at'] ?? ''),
            triggeredBy: (string) ($data['triggered_by'] ?? ''),
            appVersion: isset($data['app_version']) ? (string) $data['app_version'] : null,
            appChangelogHash: (string) ($data['app_changelog_hash'] ?? ''),
            phpVersion: (string) ($data['php_version'] ?? ''),
            laravelVersion: (string) ($data['laravel_version'] ?? ''),
            filamentVersion: (string) ($data['filament_version'] ?? ''),
            livewireVersion: (string) ($data['livewire_version'] ?? ''),
            composerLockHash: (string) ($data['composer_lock_hash'] ?? ''),
            packageLockHash: (string) ($data['package_lock_hash'] ?? ''),
            dbSchemaVersion: (string) ($data['db_schema_version'] ?? ''),
            dbMigrations: $dbMigrations,
            extensions: $extensions,
            dbSizeBytes: (int) ($data['db_size_bytes'] ?? 0),
            files: $files,
            hashDb: isset($data['hash_db']) ? (string) $data['hash_db'] : null,
            hashFiles: isset($data['hash_files']) ? (string) $data['hash_files'] : null,
            createdByUserId: isset($data['created_by_user_id']) ? (int) $data['created_by_user_id'] : null,
            sizeBytes: (int) ($data['size_bytes'] ?? 0),
            artifacts: $artifacts,
        );
    }

    public function toJson(): string
    {
        $json = json_encode(
            $this->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return $json;
    }

    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }

    /**
     * Structural invariants shared by every producer (ADR-004 section 4).
     *
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (! in_array($this->type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown backup type [{$this->type}].");
        }

        if ($this->type === self::TYPE_DB_ONLY) {
            if ($this->scope !== null) {
                throw new InvalidArgumentException('A db_only backup must have a null scope.');
            }

            if ($this->isComplete) {
                throw new InvalidArgumentException('A db_only backup must not be marked complete.');
            }

            if ($this->hashFiles !== null) {
                throw new InvalidArgumentException('A db_only backup must not carry hash_files.');
            }

            if ($this->files !== []) {
                throw new InvalidArgumentException('A db_only backup must not list files.');
            }

            return;
        }

        if (! $this->isComplete) {
            throw new InvalidArgumentException("A {$this->type} backup must be marked complete.");
        }

        if ($this->scope === null) {
            throw new InvalidArgumentException("A {$this->type} backup requires a scope.");
        }

        if (! in_array($this->scope, self::SCOPES, true)) {
            throw new InvalidArgumentException("Unknown backup scope [{$this->scope}].");
        }
    }
}
