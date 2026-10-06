<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestoreRequestService;
use App\Services\Backup\RestoreService;
use Illuminate\Console\Command;
use Throwable;

/**
 * CLI entry point for backup listing and restore (contract section 7.3).
 *
 * `LIST` prints the available backups. `CHECK` runs the read-only validation
 * of a restore request. `APPLY` performs the destructive restore. The command
 * only orchestrates; the restore logic lives in {@see RestoreService}.
 */
class RollbackCommand extends Command
{
    protected $signature = 'app:rollback
                            {action : LIST | CHECK | APPLY}
                            {id? : Restore request id (required for CHECK and APPLY)}';

    protected $description = 'List backups and run restore CHECK / APPLY from a restore request.';

    public function handle(
        BackupService $backups,
        RestoreService $restore,
        RestoreRequestService $requests,
        AuditLogger $audit,
    ): int {
        $action = strtoupper(trim((string) $this->argument('action')));
        $id = trim((string) ($this->argument('id') ?? ''));

        return match ($action) {
            'LIST' => $this->listBackups($backups),
            'CHECK' => $this->checkRequest($id, $requests, $restore, $audit),
            'APPLY' => $this->applyRequest($id, $requests, $restore),
            default => $this->unknownAction($action),
        };
    }

    private function listBackups(BackupService $backups): int
    {
        $manifests = $backups->list();

        usort(
            $manifests,
            static fn (BackupManifest $a, BackupManifest $b): int => strcmp($b->createdAt, $a->createdAt),
        );

        if ($manifests === []) {
            $this->info('No backups found.');

            return self::SUCCESS;
        }

        $this->table(
            ['backup_id', 'type', 'scope', 'created_at', 'complete', 'size_bytes'],
            array_map(
                static fn (BackupManifest $manifest): array => [
                    $manifest->backupId,
                    $manifest->type,
                    $manifest->scope ?? '—',
                    $manifest->createdAt,
                    $manifest->isComplete ? 'yes' : 'no',
                    (string) $manifest->sizeBytes,
                ],
                $manifests,
            ),
        );

        return self::SUCCESS;
    }

    private function checkRequest(
        string $id,
        RestoreRequestService $requests,
        RestoreService $restore,
        AuditLogger $audit,
    ): int {
        if ($id === '') {
            $this->error('CHECK requires a restore request id.');

            return self::FAILURE;
        }

        $request = $requests->find($id);

        if ($request === null) {
            $this->error("Restore request [{$id}] was not found.");

            return self::FAILURE;
        }

        $problems = $restore->check($request);

        if ($problems === []) {
            $this->info("Restore request [{$id}] passed all checks.");
            $this->line('  backup_id:  '.$request->backup_id);
            $this->line('  expires_at: '.$request->expires_at->toIso8601String());

            return self::SUCCESS;
        }

        $this->error("Restore request [{$id}] failed checks:");

        foreach ($problems as $problem) {
            $this->line('  - '.$problem);
        }

        // Read-only rejection: audit only, no state is changed (contract 6.4).
        $audit->log(
            action: 'restore.rejected',
            new: [
                'request_id' => $request->id,
                'backup_id' => $request->backup_id,
                'problems' => $problems,
            ],
        );

        return self::FAILURE;
    }

    private function applyRequest(
        string $id,
        RestoreRequestService $requests,
        RestoreService $restore,
    ): int {
        if ($id === '') {
            $this->error('APPLY requires a restore request id.');

            return self::FAILURE;
        }

        $request = $requests->find($id);

        if ($request === null) {
            $this->error("Restore request [{$id}] was not found.");

            return self::FAILURE;
        }

        $this->warn(
            'APPLY performs an irreversible restore. The site is put into '
            .'maintenance mode and the database and files are overwritten.',
        );

        try {
            $restore->apply($request);
        } catch (Throwable $exception) {
            $this->error('Restore failed: '.$exception->getMessage());

            if (app()->maintenanceMode()->active()) {
                $this->warn('The application is still in maintenance mode; manual intervention is required.');
            }

            return self::FAILURE;
        }

        $this->info("Restore request [{$id}] applied successfully.");

        return self::SUCCESS;
    }

    private function unknownAction(string $action): int
    {
        $this->error("Unknown action [{$action}]. Expected: LIST, CHECK or APPLY.");

        return self::FAILURE;
    }
}
