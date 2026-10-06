<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Backup\BackupService;
use App\Services\Backup\LocalBackupStorage;

/**
 * Persistence abstraction for backup artifacts.
 *
 * Stage 9 ships only {@see LocalBackupStorage}. An
 * external store (post-production, ADR-004 R1/R2) can implement the same
 * interface without touching {@see BackupService}.
 */
interface BackupStorage
{
    /**
     * Copy a local source file into the store under a relative path.
     */
    public function put(string $relativePath, string $sourcePath): void;

    /**
     * Absolute local path of a stored artifact.
     */
    public function get(string $relativePath): string;

    public function exists(string $relativePath): bool;

    public function delete(string $relativePath): void;

    /**
     * Size of a stored artifact in bytes.
     */
    public function size(string $relativePath): int;

    /**
     * Every stored manifest, decoded as an associative array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array;
}
