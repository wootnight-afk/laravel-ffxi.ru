<?php

declare(strict_types=1);

require_once __DIR__.'/Stage9RestoreSupport.php';

use App\Contracts\BackupStorage;
use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\File;

/**
 * Write the fake `mysql` client used by the restore-test tests.
 *
 * It records every invocation (arguments) in a log file, echoes a fixed count
 * for `-e` queries (the smoke test) and captures the piped dump for the load
 * step. Because the whole flow is binary-driven, the tests can assert the
 * isolated database is created, loaded and dropped without a real MySQL.
 */
function writeRestoreTestMysql(string $binary, string $log, string $sqlOut, int $count): void
{
    $template = <<<'SH'
#!/bin/sh
log=__LOG__
sql_out=__SQL_OUT__
count=__COUNT__
printf 'CALL' >> "$log"
for a in "$@"; do printf ' [%s]' "$a" >> "$log"; done
printf '\n' >> "$log"
has_e=0
for a in "$@"; do if [ "$a" = "-e" ]; then has_e=1; fi; done
if [ "$has_e" = "1" ]; then printf '%s\n' "$count"; else cat >> "$sql_out"; fi
SH;

    $script = str_replace(
        ['__LOG__', '__SQL_OUT__', '__COUNT__'],
        [escapeshellarg($log), escapeshellarg($sqlOut), (string) $count],
        $template,
    );

    File::put($binary, $script);
    chmod($binary, 0755);
}

/**
 * Boot an isolated backup store plus the fake `mysql` client.
 *
 * @return array{dir: string, log: string, sql_out: string, binary: string}
 */
function bootRestoreTestEnv(int $count = 42): array
{
    $env = bootStage9RestoreEnv();

    $binary = $env['dir'].'/bin/mysql';
    $log = $env['dir'].'/bin/mysql-calls.log';
    $sqlOut = $env['dir'].'/bin/mysql-stdin.sql';

    writeRestoreTestMysql($binary, $log, $sqlOut, $count);
    config(['backup.binaries.mysql' => $binary]);

    return ['dir' => $env['dir'], 'log' => $log, 'sql_out' => $sqlOut, 'binary' => $binary];
}

/**
 * @return array<int, string>
 */
function restoreTestCalls(string $log): array
{
    if (! is_file($log)) {
        return [];
    }

    return array_values(array_filter(explode("\n", trim((string) file_get_contents($log)))));
}

beforeEach(function () {
    $this->env = bootRestoreTestEnv();
    $this->manifest = stage9MakeBackup();
    config(['backup.restore_test.database' => 'laravel_ffxi_restore_test']);
});

afterEach(function () {
    stage9TearDown($this->env);
});

it('restores a backup into the isolated database and drops it afterwards', function () {
    $this->artisan('app:restore-test', ['id' => $this->manifest->backupId])
        ->expectsOutputToContain('succeeded')
        ->assertSuccessful();

    $calls = restoreTestCalls($this->env['log']);
    $log = implode("\n", $calls);

    expect($log)->toContain('CREATE DATABASE `laravel_ffxi_restore_test`')
        ->and($log)->toContain('DROP DATABASE IF EXISTS `laravel_ffxi_restore_test`')
        ->and(file_get_contents($this->env['sql_out']))->toContain('-- fake dump');

    // Cleanup runs last: the final mysql call drops the isolated database.
    expect(end($calls))->toContain('DROP DATABASE IF EXISTS `laravel_ffxi_restore_test`');
});

it('never references the working database', function () {
    $working = (string) config('database.connections.mysql.database');

    $this->artisan('app:restore-test', ['id' => $this->manifest->backupId])->assertSuccessful();

    expect(implode("\n", restoreTestCalls($this->env['log'])))->not->toContain($working);
});

it('refuses to run when the isolated database is the working database', function () {
    config(['backup.restore_test.database' => config('database.connections.mysql.database')]);

    $this->artisan('app:restore-test', ['id' => $this->manifest->backupId])
        ->expectsOutputToContain('working database')
        ->assertFailed();

    expect(restoreTestCalls($this->env['log']))->toBe([]);
});

it('fails for an unknown backup id', function () {
    $this->artisan('app:restore-test', ['id' => '00000000-0000-0000-0000-000000000000'])
        ->expectsOutputToContain('was not found')
        ->assertFailed();
});

it('fails when the backup has no database dump', function () {
    $manifest = app(BackupService::class)->create('site_only', 'storage_app', 'cli');

    $this->artisan('app:restore-test', ['id' => $manifest->backupId])
        ->expectsOutputToContain('does not contain a database dump')
        ->assertFailed();
});

it('fails on a dump integrity mismatch before touching any database', function () {
    File::put(
        app(BackupStorage::class)->get($this->manifest->artifacts['db']),
        'tampered',
    );

    $this->artisan('app:restore-test', ['id' => $this->manifest->backupId])
        ->expectsOutputToContain('integrity')
        ->assertFailed();

    expect(restoreTestCalls($this->env['log']))->toBe([]);
});

it('drops the isolated database even when the smoke test fails', function () {
    // The fake client reports an empty database, so the smoke test fails.
    writeRestoreTestMysql($this->env['binary'], $this->env['log'], $this->env['sql_out'], 0);
    config(['backup.binaries.mysql' => $this->env['binary']]);

    $this->artisan('app:restore-test', ['id' => $this->manifest->backupId])
        ->expectsOutputToContain('Smoke test failed')
        ->assertFailed();

    $calls = restoreTestCalls($this->env['log']);

    expect(end($calls))->toContain('DROP DATABASE IF EXISTS `laravel_ffxi_restore_test`');
});
