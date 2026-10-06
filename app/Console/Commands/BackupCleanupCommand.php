<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Applies the backup retention policy (contract section 9).
 *
 * Stage 9 skeleton: the real GFS rotation and free-space safety are added in
 * E9.3. This command intentionally performs no deletion yet.
 */
class BackupCleanupCommand extends Command
{
    protected $signature = 'app:backup-cleanup';

    protected $description = 'Apply the backup retention policy (GFS 7/4/12).';

    public function handle(): int
    {
        $this->info('Retention not implemented yet (scheduled for E9.3).');

        return self::SUCCESS;
    }
}
