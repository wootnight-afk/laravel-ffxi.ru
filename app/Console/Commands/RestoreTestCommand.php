<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Restore a backup into an isolated dev database (contract section 7.4).
 *
 * Stage 9 E9.5 ships the command skeleton only; the isolated-database restore
 * and smoke test are implemented in E9.7 (contract section 11).
 */
class RestoreTestCommand extends Command
{
    protected $signature = 'app:restore-test
                            {id : Backup id to restore into an isolated database}';

    protected $description = 'Restore a backup into an isolated dev database (implemented in E9.7).';

    public function handle(): int
    {
        $this->error(
            'app:restore-test is not implemented yet. The isolated-database '
            .'restore is scheduled for E9.7 (STAGE-9-CONTRACT section 11).',
        );

        return self::FAILURE;
    }
}
