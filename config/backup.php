<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Backup configuration (spec section 17, ADR-004)
|--------------------------------------------------------------------------
|
| Static, deployment-time parameters only. Runtime retention / safety
| parameters (backup_retention_*, min_free_space_pct) live in the
| `settings` table (ADR-004 section 5.3) and are not duplicated here.
|
*/

return [

    // Backup storage abstraction. Stage 9 implements only the local disk;
    // an external store is added later behind the same interface.
    'disk' => env('BACKUP_DISK', 'local'),

    // Root directory for backup artifacts. Must stay outside the web root.
    'path' => storage_path('backups'),

    // Manifest format version written by BackupManifest.
    'manifest_version' => 1,

    // External binaries used by the dumper / archiver. Overridable so a
    // different install path can be configured per environment.
    'binaries' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL', 'mysql'),
        'tar' => env('BACKUP_TAR', 'tar'),
    ],

    // File lock guarding concurrent backup / restore (spec section 10).
    'lock_path' => storage_path('framework/backup.lock'),

    // External copy is out of scope for Stage 9 (ADR-004 section 8.2).
    'external' => [
        'enabled' => false,
    ],

];
