<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupRetention;
use Illuminate\Console\Command;

/**
 * Applies the backup retention policy (contract section 9).
 *
 * Deletes backups that fall outside the GFS 7/4/12 protected set. Runs under
 * the shared backup lock so a cleanup cannot race a backup or restore.
 */
class BackupCleanupCommand extends Command
{
    protected $signature = 'app:backup-cleanup';

    protected $description = 'Apply the backup retention policy (GFS 7/4/12).';

    public function handle(BackupRetention $retention, BackupLock $lock): int
    {
        if (! $lock->acquire('backup')) {
            $this->error('Another backup or restore is already running.');

            return self::FAILURE;
        }

        try {
            $deleted = $retention->apply();
        } finally {
            $lock->release('backup');
        }

        $this->info("Deleted {$deleted} expired backup(s).");

        return self::SUCCESS;
    }
}
