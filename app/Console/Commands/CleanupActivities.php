<?php

namespace App\Console\Commands;

use App\Services\SettingsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CleanupActivities extends Command
{
    protected $signature = 'app:cleanup-activities';

    protected $description = 'Delete community activities outside the configured retention period.';

    public function handle(SettingsRepository $settings): int
    {
        $retentionDays = $settings->int('activity_retention_days', 180);
        if ($retentionDays < 0) {
            throw new InvalidArgumentException('activity_retention_days must be zero or greater.');
        }

        $deleted = DB::table('activities')
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("Deleted {$deleted} expired activities.");

        return self::SUCCESS;
    }
}
