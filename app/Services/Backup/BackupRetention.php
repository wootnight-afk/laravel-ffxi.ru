<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Contracts\BackupStorage;
use App\Services\SettingsRepository;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Backup retention (GFS 7/4/12) and free-space safety
 * (ADR-004 section 5, contract section 9).
 *
 * Retention parameters and the minimum free-space percentage are runtime
 * settings, so they have a single source of truth (the `settings` table).
 * The GFS selection itself is pure: given the manifest list and a reference
 * time it returns the protected and expired sets without touching the
 * filesystem, which keeps it easy to assert.
 */
class BackupRetention
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly BackupStorage $storage,
    ) {}

    /**
     * Configured GFS buckets.
     *
     * @return array{daily: int, weekly: int, monthly: int}
     */
    public function retention(): array
    {
        return [
            'daily' => max(0, $this->settings->int('backup_retention_daily', 7)),
            'weekly' => max(0, $this->settings->int('backup_retention_weekly', 4)),
            'monthly' => max(0, $this->settings->int('backup_retention_monthly', 12)),
        ];
    }

    public function minFreeSpacePercent(): int
    {
        return max(0, $this->settings->int('min_free_space_pct', 5));
    }

    /**
     * Backup ids protected by the GFS rotation.
     *
     * For each of the last N calendar days, M ISO weeks and K calendar months
     * (relative to `$now`) the newest backup in that bucket is kept. The union
     * of the three groups is the protected set (ADR-004 section 5.1).
     *
     * @param  array<int, BackupManifest>  $manifests
     * @return array<int, string>
     */
    public function protectedBackupIds(array $manifests, ?DateTimeInterface $now = null): array
    {
        $now = Carbon::instance($now ?? Carbon::now())->utc();
        $retention = $this->retention();

        $dailyKeys = [];
        $weeklyKeys = [];
        $monthlyKeys = [];

        for ($offset = 0; $offset < $retention['daily']; $offset++) {
            $dailyKeys[$now->copy()->subDays($offset)->toDateString()] = true;
        }

        for ($offset = 0; $offset < $retention['weekly']; $offset++) {
            $weeklyKeys[$this->isoWeekKey($now->copy()->subWeeks($offset))] = true;
        }

        for ($offset = 0; $offset < $retention['monthly']; $offset++) {
            $monthlyKeys[$now->copy()->startOfMonth()->subMonths($offset)->format('Y-m')] = true;
        }

        $protected = [];
        $dailySeen = [];
        $weeklySeen = [];
        $monthlySeen = [];

        foreach ($this->sortByCreatedAtDesc($manifests) as $manifest) {
            $created = Carbon::parse($manifest->createdAt)->utc();
            $day = $created->toDateString();
            $week = $this->isoWeekKey($created);
            $month = $created->format('Y-m');

            if (isset($dailyKeys[$day]) && ! isset($dailySeen[$day])) {
                $dailySeen[$day] = true;
                $protected[$manifest->backupId] = true;
            }

            if (isset($weeklyKeys[$week]) && ! isset($weeklySeen[$week])) {
                $weeklySeen[$week] = true;
                $protected[$manifest->backupId] = true;
            }

            if (isset($monthlyKeys[$month]) && ! isset($monthlySeen[$month])) {
                $monthlySeen[$month] = true;
                $protected[$manifest->backupId] = true;
            }
        }

        return array_keys($protected);
    }

    /**
     * Backups outside every retention bucket, i.e. deletion candidates.
     *
     * @param  array<int, BackupManifest>  $manifests
     * @return array<int, BackupManifest>
     */
    public function expired(array $manifests, ?DateTimeInterface $now = null): array
    {
        $protected = array_flip($this->protectedBackupIds($manifests, $now));

        return array_values(array_filter(
            $manifests,
            static fn (BackupManifest $manifest): bool => ! isset($protected[$manifest->backupId]),
        ));
    }

    /**
     * Delete every expired backup and return the number removed.
     */
    public function apply(?DateTimeInterface $now = null): int
    {
        $deleted = 0;

        foreach ($this->expired($this->manifests(), $now) as $manifest) {
            $this->deleteArtifacts($manifest);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Abort a backup that would leave less than the configured minimum share
     * of the backup volume free (ADR-004 section 5.2).
     *
     * @throws RuntimeException
     */
    public function ensureSpaceAvailable(int $expectedBytes): void
    {
        $probe = $this->spaceProbePath();

        $free = @disk_free_space($probe);
        $total = @disk_total_space($probe);

        if ($free === false || $total === false || $total <= 0) {
            // The filesystem cannot be inspected; do not block the backup.
            return;
        }

        $expectedBytes = max(0, $expectedBytes);
        $percent = $this->minFreeSpacePercent();
        $required = (int) ceil($total * $percent / 100);
        $freeAfter = $free - $expectedBytes;

        if ($freeAfter < $required) {
            throw new RuntimeException(sprintf(
                'Not enough free disk space for a backup: %d bytes free, ~%d bytes required, '
                .'and at least %d%% (%d bytes) must remain free.',
                $free,
                $expectedBytes,
                $percent,
                $required,
            ));
        }
    }

    /**
     * @return array<int, BackupManifest>
     */
    public function manifests(): array
    {
        return array_map(
            static fn (array $data): BackupManifest => BackupManifest::fromArray($data),
            $this->storage->list(),
        );
    }

    private function deleteArtifacts(BackupManifest $manifest): void
    {
        foreach ($manifest->artifacts as $relativePath) {
            if ($this->storage->exists($relativePath)) {
                $this->storage->delete($relativePath);
            }
        }

        $manifestPath = $this->manifestRelativePath($manifest);

        if ($this->storage->exists($manifestPath)) {
            $this->storage->delete($manifestPath);
        }
    }

    private function manifestRelativePath(BackupManifest $manifest): string
    {
        return Carbon::parse($manifest->createdAt)->utc()->format('Y/m')
            .'/'.$manifest->backupId.'.manifest.json';
    }

    private function spaceProbePath(): string
    {
        $path = (string) config('backup.path');

        while ($path !== '' && ! is_dir($path)) {
            $parent = dirname($path);

            if ($parent === $path) {
                break;
            }

            $path = $parent;
        }

        return $path !== '' ? $path : sys_get_temp_dir();
    }

    private function isoWeekKey(Carbon $moment): string
    {
        return sprintf('%04d-W%02d', $moment->isoWeekYear, $moment->isoWeek);
    }

    /**
     * @param  array<int, BackupManifest>  $manifests
     * @return array<int, BackupManifest>
     */
    private function sortByCreatedAtDesc(array $manifests): array
    {
        usort(
            $manifests,
            static fn (BackupManifest $a, BackupManifest $b): int => strcmp($b->createdAt, $a->createdAt),
        );

        return $manifests;
    }
}
