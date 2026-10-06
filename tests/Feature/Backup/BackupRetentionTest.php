<?php

use App\Contracts\BackupStorage;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupRetention;
use App\Services\Backup\LocalBackupStorage;
use App\Services\SettingsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->storageDir = sys_get_temp_dir().'/ffxi-retention-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->storageDir);
    config(['backup.path' => $this->storageDir]);

    $this->app->instance(BackupStorage::class, new LocalBackupStorage);
    $this->retention = $this->app->make(BackupRetention::class);
});

afterEach(function () {
    Carbon::setTestNow();
    File::deleteDirectory($this->storageDir);
});

function retentionManifest(string $id, string $createdAt): BackupManifest
{
    return BackupManifest::fromArray([
        'backup_id' => $id,
        'type' => 'db_only',
        'scope' => null,
        'is_complete' => false,
        'created_at' => $createdAt,
        'artifacts' => [],
    ]);
}

it('exposes the configured GFS buckets with their defaults', function () {
    expect($this->retention->retention())
        ->toBe(['daily' => 7, 'weekly' => 4, 'monthly' => 12]);
    expect($this->retention->minFreeSpacePercent())->toBe(5);
});

it('protects the newest backup of each bucket and expires the rest', function () {
    $now = Carbon::parse('2026-10-06 12:00:00', 'UTC');

    $manifests = [
        retentionManifest('today', '2026-10-06T10:00:00+00:00'),
        retentionManifest('yesterday', '2026-10-05T10:00:00+00:00'),
        retentionManifest('three-weeks', '2026-09-20T10:00:00+00:00'),
        retentionManifest('this-year', '2026-01-15T10:00:00+00:00'),
        retentionManifest('last-year', '2025-01-15T10:00:00+00:00'),
        retentionManifest('mid-2025', '2025-06-01T10:00:00+00:00'),
    ];

    $protected = $this->retention->protectedBackupIds($manifests, $now);

    expect($protected)->toContain('today', 'yesterday', 'three-weeks', 'this-year');
    expect($protected)->not->toContain('last-year', 'mid-2025');

    $expired = array_map(
        static fn (BackupManifest $manifest): string => $manifest->backupId,
        $this->retention->expired($manifests, $now),
    );

    expect($expired)->toEqualCanonicalizing(['last-year', 'mid-2025']);
});

it('keeps only the newest backup within a single day', function () {
    $now = Carbon::parse('2026-10-06 12:00:00', 'UTC');

    $manifests = [
        retentionManifest('newer', '2026-10-06T11:00:00+00:00'),
        retentionManifest('older', '2026-10-06T09:00:00+00:00'),
    ];

    $expired = array_map(
        static fn (BackupManifest $manifest): string => $manifest->backupId,
        $this->retention->expired($manifests, $now),
    );

    expect($expired)->toBe(['older']);
});

it('reads the retention buckets from settings', function () {
    $settings = app(SettingsRepository::class);
    $settings->set('backup_retention_daily', 1);
    $settings->set('backup_retention_weekly', 0);
    $settings->set('backup_retention_monthly', 0);

    $retention = app(BackupRetention::class);

    $now = Carbon::parse('2026-10-06 12:00:00', 'UTC');
    $manifests = [
        retentionManifest('today', '2026-10-06T10:00:00+00:00'),
        retentionManifest('yesterday', '2026-10-05T10:00:00+00:00'),
    ];

    expect($retention->protectedBackupIds($manifests, $now))->toBe(['today']);
    expect($retention->retention())->toBe(['daily' => 1, 'weekly' => 0, 'monthly' => 0]);
});

it('protects nothing when every bucket is disabled', function () {
    $settings = app(SettingsRepository::class);
    $settings->set('backup_retention_daily', 0);
    $settings->set('backup_retention_weekly', 0);
    $settings->set('backup_retention_monthly', 0);

    $retention = app(BackupRetention::class);
    $now = Carbon::parse('2026-10-06 12:00:00', 'UTC');
    $manifests = [retentionManifest('only', '2026-10-06T10:00:00+00:00')];

    expect($retention->protectedBackupIds($manifests, $now))->toBe([]);
    expect($retention->expired($manifests, $now))->toHaveCount(1);
});

it('aborts when the backup would leave less than the minimum free space', function () {
    app(SettingsRepository::class)->set('min_free_space_pct', 100);

    expect(fn () => $this->retention->ensureSpaceAvailable(1024))
        ->toThrow(RuntimeException::class, 'Not enough free disk space');
});

it('allows the backup when the minimum free space is satisfied', function () {
    app(SettingsRepository::class)->set('min_free_space_pct', 0);

    expect(fn () => $this->retention->ensureSpaceAvailable(1024))
        ->not->toThrow(RuntimeException::class);
});
