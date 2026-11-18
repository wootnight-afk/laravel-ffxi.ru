<?php

namespace App\Console\Commands;

use App\Services\LegendLocaleBackfill;
use App\Services\ProfileLegendLocaleBackfill;
use App\Support\LegendLanguage;
use Illuminate\Console\Command;

/**
 * Redistribution of the existing single legend into the RU and EN versions
 * (STAGE-1 §16.2).
 */
class LegendLocaleCommand extends Command
{
    protected $signature = 'characters:legend-locale
        {--report : Show how the existing legends would be split}
        {--dry-run : Show the concrete plan without writing}
        {--apply : Copy the legends into the localized columns}
        {--profiles : Work on profile descriptions instead of characters}';

    protected $description = 'Split the existing character legends (or profile descriptions) into RU and EN.';

    public function handle(LegendLocaleBackfill $backfill, ProfileLegendLocaleBackfill $profiles): int
    {
        $backfill = $this->option('profiles') ? $profiles : $backfill;
        $modes = array_filter([
            'report' => (bool) $this->option('report'),
            'dry-run' => (bool) $this->option('dry-run'),
            'apply' => (bool) $this->option('apply'),
        ]);

        if (count($modes) !== 1) {
            $this->error('Choose exactly one mode: --report, --dry-run or --apply.');

            return self::FAILURE;
        }

        if ($modes['apply'] ?? false) {
            $summary = $backfill->apply();

            $this->line('Moved to RU: '.$summary[LegendLanguage::RU]);
            $this->line('Moved to EN: '.$summary[LegendLanguage::EN]);
            $this->line('Ambiguous (kept in RU, review): '.$summary[LegendLanguage::AMBIGUOUS]);
            $this->line('Skipped: '.$summary['skipped']);
            $this->line('Errors: '.$summary['errors']);

            $this->info('The legacy `legend` column was not modified.');

            return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $report = $backfill->report();

        $this->line(($this->option('profiles') ? 'Accounts' : 'Characters').': '.$report['total']);
        $this->line('With a legacy legend: '.$report['with_legend']);
        $this->line('Would move to RU: '.$report[LegendLanguage::RU]);
        $this->line('Would move to EN: '.$report[LegendLanguage::EN]);
        $this->line('Ambiguous: '.$report[LegendLanguage::AMBIGUOUS]);
        $this->line('Already localized: '.$report['already_localized']);

        foreach ($report['plan'] as $row) {
            $this->line(sprintf(
                '  #%d %s -> %s (%s), %d chars',
                $row['id'],
                $row['name'],
                $row['locale'],
                $row['column'],
                $row['length'],
            ));
        }

        if ($report['ambiguous_list'] !== []) {
            $this->warn('Not classified unambiguously (kept in `legend`, will go to RU on --apply):');

            foreach ($report['ambiguous_list'] as $row) {
                $this->line(sprintf('  #%d %s (%d chars)', $row['id'], $row['name'], $row['length']));
            }
        }

        if ($modes['dry-run'] ?? false) {
            $this->info('Dry run: nothing was written.');
        }

        return self::SUCCESS;
    }
}
