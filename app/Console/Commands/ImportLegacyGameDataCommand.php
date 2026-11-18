<?php

namespace App\Console\Commands;

use App\Services\LegacyGameDataImporter;
use App\Services\LegacyGameDataRollback;
use App\Services\LegacyGameDataValues;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * CLI for the legacy game data transfer (STAGE-1 §6.3).
 *
 * Exactly one mode per run. `--apply` writes only into empty target fields
 * unless `--overwrite` is given explicitly; in production it also requires
 * `--force`. Rollback is always explicit and never automatic.
 */
class ImportLegacyGameDataCommand extends Command
{
    protected $signature = 'characters:legacy-game-data
        {--report : Show the distribution only}
        {--dry-run : Show the planned actions without writing}
        {--apply : Apply the transfer}
        {--overwrite= : Comma separated fields to overwrite (race,main_job,legend)}
        {--rollback= : Roll back the given batch id}
        {--force : Allow --apply in production}';

    protected $description = 'Transfer legacy account game data (race, main job, legend) to characters.';

    public function handle(LegacyGameDataImporter $importer, LegacyGameDataRollback $rollback): int
    {
        $rollbackId = $this->option('rollback');

        $modes = array_filter([
            'report' => (bool) $this->option('report'),
            'dry-run' => (bool) $this->option('dry-run'),
            'apply' => (bool) $this->option('apply'),
            'rollback' => $rollbackId !== null,
        ]);

        if (count($modes) !== 1) {
            $this->error('Choose exactly one mode: --report, --dry-run, --apply or --rollback=ID.');

            return self::FAILURE;
        }

        $overwrite = [];

        if ($this->option('overwrite') !== null) {
            if (! isset($modes['apply'])) {
                $this->error('--overwrite requires --apply.');

                return self::FAILURE;
            }

            $overwrite = $this->parseOverwrite((string) $this->option('overwrite'));

            if ($overwrite === null) {
                return self::FAILURE;
            }
        }

        if (isset($modes['report'])) {
            $this->renderReport($importer->report());

            return self::SUCCESS;
        }

        if (isset($modes['dry-run'])) {
            $result = $importer->dryRun();
            $this->renderReport($result['report']);
            $this->renderPlan($result['plan']);

            return self::SUCCESS;
        }

        if (isset($modes['rollback'])) {
            $summary = $rollback->rollback((string) $rollbackId);
            $this->info('Rollback summary for batch '.$rollbackId);
            $this->table(['Result', 'Count'], [
                ['reverted', $summary['reverted'] ?? 0],
                ['skipped_modified', $summary['skipped_modified'] ?? 0],
                ['skipped_conflict', $summary['skipped_conflict'] ?? 0],
                ['errors', $summary['errors'] ?? 0],
            ]);

            return ($summary['errors'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
        }

        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('--apply in production requires --force after a reviewed --dry-run.');

            return self::FAILURE;
        }

        $batchId = (string) Str::uuid();
        $summary = $importer->apply($batchId, $overwrite);

        $this->info('Applied batch '.$batchId);
        $this->table(['Result', 'Count'], [
            ['processed', $summary['processed'] ?? 0],
            ['applied', $summary['applied'] ?? 0],
            ['skipped_conflict', $summary['skipped_conflict'] ?? 0],
            ['skipped_no_target', $summary['skipped_no_target'] ?? 0],
            ['errors', $summary['errors'] ?? 0],
        ]);

        return ($summary['errors'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>|null
     */
    private function parseOverwrite(string $raw): ?array
    {
        $fields = $raw === ''
            ? LegacyGameDataValues::FIELDS
            : array_values(array_filter(array_map('trim', explode(',', $raw))));

        $invalid = array_diff($fields, LegacyGameDataValues::FIELDS);

        if ($fields === [] || $invalid !== []) {
            $this->error('Unsupported --overwrite fields: '.implode(', ', $invalid ?: ['(empty)']).'. Allowed: race, main_job, legend.');

            return null;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(array $report): void
    {
        $this->info('Legacy game data distribution');
        $this->table(['Metric', 'Value'], [
            ['accounts with legacy values', $report['accounts'] ?? 0],
            ['no character', $report['zero'] ?? 0],
            ['exactly one character', $report['one'] ?? 0],
            ['two or more characters', $report['many'] ?? 0],
            ['race values', $report['fields']['race'] ?? 0],
            ['main job values', $report['fields']['main_job'] ?? 0],
            ['legend values', $report['fields']['legend'] ?? 0],
            ['planned transfers', $report['planned'] ?? 0],
            ['conflicts: race', $report['conflicts']['race'] ?? 0],
            ['conflicts: main job', $report['conflicts']['main_job'] ?? 0],
            ['conflicts: legend', $report['conflicts']['legend'] ?? 0],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $plan
     */
    private function renderPlan(array $plan): void
    {
        $this->info('Planned actions ('.count($plan).' accounts)');

        if ($plan === []) {
            return;
        }

        $rows = [];

        foreach ($plan as $account) {
            if (($account['reason'] ?? null) !== null) {
                $rows[] = [$account['user_id'], $account['reason'], '—', '—'];

                continue;
            }

            foreach ($account['actions'] as $action) {
                $rows[] = [
                    $account['user_id'],
                    $action['field'],
                    $action['status'],
                    (string) ($action['target'] ?? 'NULL'),
                ];
            }
        }

        $this->table(['Account', 'Field', 'Decision', 'Current value'], $rows);
    }
}
