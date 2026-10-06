<?php

declare(strict_types=1);

use App\Contracts\BackupStorage;
use App\Models\User;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\FilesArchiver;
use App\Services\Backup\LocalBackupStorage;
use Illuminate\Support\Facades\File;

/**
 * Shared fixtures for the Stage 9 E9.5 restore tests.
 *
 * Builds an isolated backup store, fakes the dump/archive producers and
 * installs fake `mysql` / `tar` executables so the restore flow can be
 * exercised without touching the real database or project files.
 *
 * @return array{dir: string, storage: LocalBackupStorage, mysql_out: string, tar_out: string}
 */
function bootStage9RestoreEnv(): array
{
    $dir = sys_get_temp_dir().'/ffxi-e95-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($dir);

    config([
        'backup.path' => $dir.'/store',
        'backup.lock_path' => $dir.'/backup.lock',
    ]);

    $storage = new LocalBackupStorage;
    app()->instance(BackupStorage::class, $storage);

    app()->instance(DatabaseDumper::class, new class extends DatabaseDumper
    {
        public function dump(string $outputPath): array
        {
            file_put_contents($outputPath, gzencode("-- fake dump\n"));

            return ['path' => $outputPath, 'hash' => hash_file('sha256', $outputPath)];
        }

        public function available(): bool
        {
            return true;
        }
    });

    app()->instance(FilesArchiver::class, new class extends FilesArchiver
    {
        public function archive(string $scope, string $outputPath): array
        {
            file_put_contents($outputPath, gzencode('fake-files'));

            return [
                'path' => $outputPath,
                'hash' => hash_file('sha256', $outputPath),
                'files' => ['storage/app'],
            ];
        }

        public function estimateSize(string $scope): int
        {
            return 1024;
        }

        public function available(): bool
        {
            return true;
        }
    });

    $bin = $dir.'/bin';
    File::ensureDirectoryExists($bin);

    $mysqlOut = $bin.'/mysql-out.sql';
    File::put($bin.'/mysql', "#!/bin/sh\ncat > ".escapeshellarg($mysqlOut)."\n");
    chmod($bin.'/mysql', 0755);

    $tarOut = $bin.'/tar-out.txt';
    File::put($bin.'/tar', "#!/bin/sh\nprintf '%s\\n' \"\$@\" > ".escapeshellarg($tarOut)."\n");
    chmod($bin.'/tar', 0755);

    config([
        'backup.binaries.mysql' => $bin.'/mysql',
        'backup.binaries.tar' => $bin.'/tar',
    ]);

    return ['dir' => $dir, 'storage' => $storage, 'mysql_out' => $mysqlOut, 'tar_out' => $tarOut];
}

function stage9Admin(string $password = 'restore-secret'): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => bcrypt($password),
    ]);
    $user->assignRole('admin');

    return $user;
}

function stage9MakeBackup(): BackupManifest
{
    return app(BackupService::class)->create('full', 'storage_app', 'cli');
}

/**
 * @param  array{dir: string}  $env
 */
function stage9TearDown(array $env): void
{
    File::deleteDirectory($env['dir']);
    File::deleteDirectory(storage_path('framework/backup-tmp'));
}
