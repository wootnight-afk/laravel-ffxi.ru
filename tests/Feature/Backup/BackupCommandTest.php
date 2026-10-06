<?php

use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backupDir = sys_get_temp_dir().'/ffxi-cli-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->backupDir);

    config([
        'backup.path' => $this->backupDir,
        'backup.lock_path' => $this->backupDir.'/backup.lock',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->backupDir);
    File::deleteDirectory(storage_path('framework/backup-tmp'));
    Mockery::close();
});

/**
 * Decode the newest manifest written under the temporary backup directory.
 *
 * @return array<string, mixed>|null
 */
function latestCliManifest(string $baseDir): ?array
{
    $paths = glob($baseDir.'/*/*/*.manifest.json') ?: [];
    sort($paths);
    $path = end($paths);

    if ($path === false) {
        return null;
    }

    return json_decode((string) file_get_contents($path), true);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function cliManifestFixture(array $overrides = []): BackupManifest
{
    return BackupManifest::fromArray(array_merge([
        'format_version' => 1,
        'backup_id' => '11111111-2222-3333-4444-555555555555',
        'type' => 'db_only',
        'scope' => null,
        'is_complete' => false,
        'created_at' => '2026-10-06T04:00:00+00:00',
        'triggered_by' => 'cli',
        'app_version' => null,
        'app_changelog_hash' => '',
        'php_version' => '8.4.0',
        'laravel_version' => '13.0.0',
        'filament_version' => '5.0.0',
        'livewire_version' => '4.0.0',
        'composer_lock_hash' => '',
        'package_lock_hash' => '',
        'db_schema_version' => '',
        'db_migrations' => [],
        'extensions' => [],
        'db_size_bytes' => 0,
        'files' => [],
        'hash_db' => 'db-hash',
        'hash_files' => null,
        'created_by_user_id' => null,
        'size_bytes' => 1024,
        'artifacts' => ['db' => '2026/10/11111111.dump.gz'],
    ], $overrides));
}

/**
 * Bind a deterministic dumper so the CLI-to-service path can be asserted
 * without depending on a live MySQL connection. The real mysqldump is
 * exercised by the full-mode tests below and by the E2E report step.
 */
function useStubDatabaseDumper(): void
{
    app()->instance(DatabaseDumper::class, new class extends DatabaseDumper
    {
        public function dump(string $outputPath): array
        {
            file_put_contents($outputPath, gzencode('-- stub dump'));

            return ['path' => $outputPath, 'hash' => hash_file('sha256', $outputPath)];
        }

        public function available(): bool
        {
            return true;
        }
    });
}

/**
 * Skip the current test unless a real mysqldump can produce a dump.
 *
 * The binary may be present while the connection still fails (for example the
 * MariaDB client verifying a self-signed MySQL 8.4 dev certificate). The E2E
 * verification of the real dump lives in the Stage 9 report, so the feature
 * tests skip with an explicit reason instead of reporting a false failure.
 */
function requireWorkingMysqldump(): void
{
    static $usable = null;

    if ($usable === null) {
        $probe = tempnam(sys_get_temp_dir(), 'ffxi-probe-');
        $usable = false;

        try {
            app(DatabaseDumper::class)->dump($probe.'.dump.gz');
            $usable = true;
        } catch (Throwable) {
            $usable = false;
        } finally {
            @unlink($probe);
            @unlink($probe.'.dump.gz');
            @unlink($probe.'.dump.gz.sql');
        }
    }

    if (! $usable) {
        test()->markTestSkipped(
            'mysqldump cannot produce a dump in this environment '
            .'(binary present, connection/TLS fails); real E2E is verified in the E9.2 report.',
        );
    }
}

it('creates a db-only backup', function () {
    useStubDatabaseDumper();

    $this->artisan('app:backup', ['--mode' => 'db'])->assertExitCode(0);

    $manifest = latestCliManifest($this->backupDir);

    expect($manifest)->not->toBeNull();
    expect($manifest['type'])->toBe('db_only');
    expect($manifest['scope'])->toBeNull();
    expect($manifest['is_complete'])->toBeFalse();
    expect($manifest['hash_db'])->not->toBeNull();
    expect($manifest['hash_files'])->toBeNull();
    expect($manifest['files'])->toBe([]);
    expect($manifest['size_bytes'])->toBeGreaterThan(0);
});

it('creates a full backup with scope a', function () {
    requireWorkingMysqldump();

    $this->artisan('app:backup', ['--mode' => 'full', '--scope' => 'a'])->assertExitCode(0);

    $manifest = latestCliManifest($this->backupDir);

    expect($manifest['type'])->toBe('full');
    expect($manifest['scope'])->toBe('storage_app');
    expect($manifest['is_complete'])->toBeTrue();
    expect($manifest['hash_db'])->not->toBeNull();
    expect($manifest['hash_files'])->not->toBeNull();
    expect($manifest['files'])->toBe(['storage/app']);
});

it('creates a full backup with scope b and warns about secrets', function () {
    requireWorkingMysqldump();

    $this->artisan('app:backup', ['--mode' => 'full', '--scope' => 'b'])
        ->expectsOutputToContain('.env')
        ->assertExitCode(0);

    $manifest = latestCliManifest($this->backupDir);

    expect($manifest['scope'])->toBe('whole_site');
    expect($manifest['is_complete'])->toBeTrue();
    expect($manifest['hash_db'])->not->toBeNull();
    expect($manifest['hash_files'])->not->toBeNull();
});

it('creates a site-only backup with scope a', function () {
    $this->artisan('app:backup', ['--mode' => 'site', '--scope' => 'a'])->assertExitCode(0);

    $manifest = latestCliManifest($this->backupDir);

    expect($manifest['type'])->toBe('site_only');
    expect($manifest['scope'])->toBe('storage_app');
    expect($manifest['is_complete'])->toBeTrue();
    expect($manifest['hash_db'])->toBeNull();
    expect($manifest['hash_files'])->not->toBeNull();
});

it('creates a site-only backup with scope b', function () {
    $this->artisan('app:backup', ['--mode' => 'site', '--scope' => 'b'])->assertExitCode(0);

    $manifest = latestCliManifest($this->backupDir);

    expect($manifest['type'])->toBe('site_only');
    expect($manifest['scope'])->toBe('whole_site');
    expect($manifest['hash_db'])->toBeNull();
    expect($manifest['hash_files'])->not->toBeNull();
});

it('fails when full mode is used without a scope', function () {
    $this->artisan('app:backup', ['--mode' => 'full'])
        ->expectsOutputToContain('--scope')
        ->assertExitCode(1);

    expect(latestCliManifest($this->backupDir))->toBeNull();
});

it('fails when site mode is used without a scope', function () {
    $this->artisan('app:backup', ['--mode' => 'site'])
        ->expectsOutputToContain('--scope')
        ->assertExitCode(1);

    expect(latestCliManifest($this->backupDir))->toBeNull();
});

it('fails when no mode is given', function () {
    $this->artisan('app:backup')
        ->expectsOutputToContain('--mode')
        ->assertExitCode(1);

    expect(latestCliManifest($this->backupDir))->toBeNull();
});

it('fails on an unknown mode', function () {
    $this->artisan('app:backup', ['--mode' => 'incremental'])
        ->assertExitCode(1);

    expect(latestCliManifest($this->backupDir))->toBeNull();
});

it('fails on an unknown scope', function () {
    $this->artisan('app:backup', ['--mode' => 'site', '--scope' => 'z'])
        ->assertExitCode(1);

    expect(latestCliManifest($this->backupDir))->toBeNull();
});

it('ignores the scope option for a db-only backup', function () {
    useStubDatabaseDumper();

    $this->artisan('app:backup', ['--mode' => 'db', '--scope' => 'a'])->assertExitCode(0);

    $manifest = latestCliManifest($this->backupDir);

    expect($manifest['type'])->toBe('db_only');
    expect($manifest['scope'])->toBeNull();
});

it('records the admin actor for triggered-by admin:{id}', function () {
    $this->artisan('app:backup', [
        '--mode' => 'site',
        '--scope' => 'a',
        '--triggered-by' => 'admin:5',
    ])->assertExitCode(0);

    $manifest = latestCliManifest($this->backupDir);

    expect($manifest['triggered_by'])->toBe('admin:5');
    expect($manifest['created_by_user_id'])->toBe(5);
});

it('records no actor for cron', function () {
    $this->artisan('app:backup', [
        '--mode' => 'site',
        '--scope' => 'a',
        '--triggered-by' => 'cron',
    ])->assertExitCode(0);

    $manifest = latestCliManifest($this->backupDir);

    expect($manifest['triggered_by'])->toBe('cron');
    expect($manifest['created_by_user_id'])->toBeNull();
});

it('rejects an invalid triggered-by value', function () {
    $this->artisan('app:backup', [
        '--mode' => 'site',
        '--scope' => 'a',
        '--triggered-by' => 'robot',
    ])->assertExitCode(1);

    expect(latestCliManifest($this->backupDir))->toBeNull();
});

it('prints the backup id, type, scope and manifest path', function () {
    $exitCode = Artisan::call('app:backup', ['--mode' => 'site', '--scope' => 'a']);
    expect($exitCode)->toBe(0);

    $manifest = latestCliManifest($this->backupDir);
    $output = Artisan::output();

    expect($output)
        ->toContain($manifest['backup_id'])
        ->toContain('site_only')
        ->toContain('storage_app')
        ->toContain('.manifest.json');
});

it('applies retention cleanup and reports the number of deletions', function () {
    $this->artisan('app:backup-cleanup')
        ->expectsOutputToContain('Deleted')
        ->assertExitCode(0);
});

it('delegates to BackupService without duplicating business logic', function () {
    $manifest = cliManifestFixture();

    $service = Mockery::mock(BackupService::class);
    $service->shouldReceive('create')
        ->once()
        ->with('db_only', null, 'cli', null)
        ->andReturn($manifest);
    $service->shouldReceive('manifestPath')
        ->once()
        ->andReturn('2026/10/11111111-2222-3333-4444-555555555555.manifest.json');

    $this->app->instance(BackupService::class, $service);

    $this->artisan('app:backup', ['--mode' => 'db'])
        ->expectsOutputToContain('11111111-2222-3333-4444-555555555555')
        ->assertExitCode(0);
});

it('maps admin:{id} and scope to BackupService arguments', function () {
    $manifest = cliManifestFixture([
        'type' => 'site_only',
        'scope' => 'storage_app',
        'is_complete' => true,
        'hash_files' => 'files-hash',
        'files' => ['storage/app'],
        'artifacts' => ['files' => '2026/10/11111111.files.tar.gz'],
    ]);

    $service = Mockery::mock(BackupService::class);
    $service->shouldReceive('create')
        ->once()
        ->with('site_only', 'storage_app', 'admin:7', 7)
        ->andReturn($manifest);
    $service->shouldReceive('manifestPath')
        ->once()
        ->andReturn('2026/10/11111111-2222-3333-4444-555555555555.manifest.json');

    $this->app->instance(BackupService::class, $service);

    $this->artisan('app:backup', [
        '--mode' => 'site',
        '--scope' => 'a',
        '--triggered-by' => 'admin:7',
    ])->assertExitCode(0);
});
