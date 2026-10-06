<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Thin CLI wrapper around {@see BackupService} (contract section 7.1).
 *
 * The command only parses and validates its options and delegates the actual
 * work; no backup business logic lives here.
 */
class BackupCommand extends Command
{
    protected $signature = 'app:backup
                            {--mode= : Backup mode: full | site | db}
                            {--scope= : Scope: a (storage_app) | b (whole_site); required for full/site}
                            {--triggered-by=cli : Actor: cli | cron | deploy | admin:{id}}';

    protected $description = 'Create a backup (full / site_only / db_only).';

    public function handle(BackupService $service): int
    {
        $mode = strtolower(trim((string) $this->option('mode')));
        $scope = strtolower(trim((string) $this->option('scope')));
        $triggeredBy = trim((string) $this->option('triggered-by'));

        $modeMap = [
            'full' => BackupManifest::TYPE_FULL,
            'site' => BackupManifest::TYPE_SITE_ONLY,
            'db' => BackupManifest::TYPE_DB_ONLY,
        ];

        if ($mode === '') {
            $this->error('The --mode option is required (full | site | db).');

            return self::FAILURE;
        }

        if (! isset($modeMap[$mode])) {
            $this->error("Unknown --mode [{$mode}]. Expected: full, site or db.");

            return self::FAILURE;
        }

        $scopeValue = $this->resolveScope($mode, $scope);

        if ($scopeValue === false) {
            return self::FAILURE;
        }

        if (! $this->isValidTriggeredBy($triggeredBy)) {
            $this->error("Invalid --triggered-by [{$triggeredBy}]. Expected: cli, cron, deploy or admin:{id}.");

            return self::FAILURE;
        }

        $actorId = str_starts_with($triggeredBy, 'admin:')
            ? (int) substr($triggeredBy, strlen('admin:'))
            : null;

        try {
            $manifest = $service->create($modeMap[$mode], $scopeValue, $triggeredBy, $actorId);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($manifest, $service->manifestPath($manifest));

        return self::SUCCESS;
    }

    /**
     * Resolve the scope option to a BackupService scope value.
     *
     * Returns false after printing an error when the combination is invalid.
     */
    private function resolveScope(string $mode, string $scope): string|null|false
    {
        if ($mode === 'db') {
            // Scope does not apply to db-only backups (contract section 5.1).
            return null;
        }

        if ($scope === '') {
            $this->error("The --scope option is required for --mode={$mode} (a | b).");

            return false;
        }

        return match ($scope) {
            'a' => BackupManifest::SCOPE_STORAGE_APP,
            'b' => BackupManifest::SCOPE_WHOLE_SITE,
            default => $this->unknownScope($scope),
        };
    }

    private function unknownScope(string $scope): false
    {
        $this->error("Unknown --scope [{$scope}]. Expected: a or b.");

        return false;
    }

    private function isValidTriggeredBy(string $triggeredBy): bool
    {
        if (in_array($triggeredBy, ['cli', 'cron', 'deploy'], true)) {
            return true;
        }

        return preg_match('/^admin:\d+$/', $triggeredBy) === 1;
    }

    private function report(BackupManifest $manifest, string $manifestPath): void
    {
        $this->newLine();
        $this->info('Backup created successfully.');
        $this->newLine();
        $this->line('  backup_id:   <fg=yellow>'.$manifest->backupId.'</>');
        $this->line('  type:        '.$manifest->type);
        $this->line('  scope:       '.($manifest->scope ?? 'null'));
        $this->line('  is_complete: '.($manifest->isComplete ? 'true' : 'false'));
        $this->line('  size_bytes:  '.$manifest->sizeBytes);
        $this->line('  manifest:    '.$manifestPath);
        $this->newLine();

        if ($manifest->scope === BackupManifest::SCOPE_WHOLE_SITE) {
            $this->warn(
                'Warning: this archive contains .env and application secrets '
                .'(ADR-004 section 3.4). Store it as a sensitive artifact.',
            );
        }
    }
}
