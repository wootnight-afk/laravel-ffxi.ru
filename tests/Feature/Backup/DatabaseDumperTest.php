<?php

use App\Services\Backup\DatabaseDumper;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->binDir = sys_get_temp_dir().'/ffxi-bin-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->binDir);

    $this->binary = $this->binDir.'/mysqldump';
    file_put_contents($this->binary, "#!/bin/sh\nexit 0\n");
    chmod($this->binary, 0755);

    $this->workDir = sys_get_temp_dir().'/ffxi-dump-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->workDir);

    config(['backup.binaries.mysqldump' => $this->binary]);
});

afterEach(function () {
    File::deleteDirectory($this->binDir);
    File::deleteDirectory($this->workDir);
});

it('reports availability based on the configured binary', function () {
    expect((new DatabaseDumper)->available())->toBeTrue();

    config(['backup.binaries.mysqldump' => $this->binDir.'/does-not-exist']);

    expect((new DatabaseDumper)->available())->toBeFalse();
});

it('throws a helpful error when mysqldump is missing', function () {
    config(['backup.binaries.mysqldump' => $this->binDir.'/does-not-exist']);

    expect(fn () => (new DatabaseDumper)->dump($this->workDir.'/dump.gz'))
        ->toThrow(RuntimeException::class, 'mysqldump was not found');
});

it('builds a safe mysqldump command and produces a gzip dump', function () {
    $captured = null;
    $credentialsPath = null;

    Process::fake(function (PendingProcess $process) use (&$captured, &$credentialsPath): mixed {
        $captured = $process->command;

        foreach ($captured as $argument) {
            if (str_starts_with($argument, '--defaults-extra-file=')) {
                $credentialsPath = substr($argument, strlen('--defaults-extra-file='));

                expect(file_exists($credentialsPath))->toBeTrue();
                expect(substr(sprintf('%o', fileperms($credentialsPath)), -4))->toBe('0600');
            }

            if (str_starts_with($argument, '--result-file=')) {
                file_put_contents(substr($argument, strlen('--result-file=')), "-- fake dump\n");
            }
        }

        return Process::result();
    });

    $output = $this->workDir.'/dump.gz';
    $result = (new DatabaseDumper)->dump($output);

    expect($captured)->toBeArray();
    expect($captured[0])->toBe($this->binary);

    foreach ([
        '--single-transaction',
        '--quick',
        '--skip-lock-tables',
        '--routines',
        '--triggers',
        '--no-tablespaces',
    ] as $option) {
        expect($captured)->toContain($option);
    }

    expect(implode(' ', $captured))->not->toContain('secret');
    expect($captured[array_key_last($captured)])->toBe((string) config('database.connections.mysql.database'));

    expect($result['path'])->toBe($output);
    expect($result['hash'])->toBe(hash_file('sha256', $output));
    expect(gzdecode((string) file_get_contents($output)))->toBe("-- fake dump\n");

    expect($credentialsPath)->not->toBeNull();
    expect(file_exists($credentialsPath))->toBeFalse();
    expect(file_exists($output.'.sql'))->toBeFalse();
});
