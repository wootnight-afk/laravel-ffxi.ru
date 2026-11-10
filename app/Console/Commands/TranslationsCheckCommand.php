<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\TranslationCompleteness;
use Illuminate\Console\Command;

/**
 * Reports translation keys that are present in one locale but missing in the
 * other (ADR-011 §2.16).
 *
 * Missing keys in the target locale are critical (non-zero exit code); keys
 * missing in the reference locale are informational. A key that exists only
 * in the target is also the "extra" key on the reference side, so both
 * directions are covered by the two lists.
 */
final class TranslationsCheckCommand extends Command
{
    protected $signature = 'app:translations:check
                            {--path= : Locale root directory (defaults to lang_path())}
                            {--reference=ru : Reference locale}
                            {--target=en : Locale that must contain every reference key}';

    protected $description = 'Report translation keys missing between two locales.';

    public function handle(TranslationCompleteness $check): int
    {
        $path = $this->option('path');
        $reference = (string) $this->option('reference');
        $target = (string) $this->option('target');
        $root = is_string($path) && $path !== '' ? $path : lang_path();

        if (! is_dir($root)) {
            $this->error("Locale directory not found: {$root}");

            return self::FAILURE;
        }

        $result = $check->compare($root, $reference, $target);

        $this->line("Locale root: {$root} (reference: {$reference}, target: {$target})");
        $this->report('Missing in '.$target.' (critical)', $result['missing_in_target'], true);
        $this->report('Missing in '.$reference.' (info)', $result['missing_in_reference'], false);

        if ($result['missing_in_target'] === []) {
            $this->info('All reference keys are present in the target locale.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function report(string $title, array $keys, bool $critical): void
    {
        $this->line($title.': '.count($keys));

        foreach ($keys as $key) {
            if ($critical) {
                $this->error('  '.$key);
            } else {
                $this->warn('  '.$key);
            }
        }
    }
}
