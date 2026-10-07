<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backup\RestoreTestService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Restore a backup into an isolated dev database (contract section 7.4).
 *
 * The restore happens in a throwaway database that is dropped again after a
 * smoke test, so the working database and the project files are never touched
 * (contract section 11). The restore logic lives in {@see RestoreTestService}.
 */
class RestoreTestCommand extends Command
{
    protected $signature = 'app:restore-test
                            {id : Backup id to restore into an isolated database}';

    protected $description = 'Restore a backup into an isolated dev database and drop it afterwards.';

    public function handle(RestoreTestService $restore): int
    {
        $id = trim((string) $this->argument('id'));

        if ($id === '') {
            $this->error('app:restore-test requires a backup id.');

            return self::FAILURE;
        }

        try {
            $report = $restore->run($id);
        } catch (Throwable $exception) {
            $this->error('Restore-test failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Restore-test for backup [{$id}] succeeded.");
        $this->line('  isolated database: '.$report['database']);
        $this->line('  tables restored:   '.$report['table_count']);
        $this->line('  migrations:        '.$report['migration_count']);
        $this->line('  smoke test:        OK');
        $this->line('The isolated database was dropped after the test.');

        return self::SUCCESS;
    }
}
