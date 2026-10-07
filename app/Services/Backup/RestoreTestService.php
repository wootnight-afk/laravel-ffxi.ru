<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Contracts\BackupStorage;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Restores a backup into an isolated development database (spec section 17,
 * STAGE-9-CONTRACT section 11).
 *
 * Unlike {@see RestoreService}, this flow never touches the working database
 * or the project files. The dump is loaded into a throwaway database that is
 * dropped again after the smoke test, so it verifies that a backup can really
 * be restored without risking the live environment.
 *
 * The isolated database lifecycle (create / drop) needs elevated rights, so
 * the command uses the dedicated account from `backup.restore_test`
 * (development: the MySQL root of the Docker stack).
 */
class RestoreTestService
{
    private const DEFAULT_TIMEOUT = 1800;

    public function __construct(
        private readonly BackupService $backups,
        private readonly BackupStorage $storage,
    ) {}

    /**
     * Restore the backup into the isolated database, smoke-test it and drop it.
     *
     * @return array{database: string, table_count: int, migration_count: int}
     *
     * @throws RuntimeException when the backup is unusable, the isolated
     *                          database is not configured separately, or any
     *                          step of the restore fails.
     */
    public function run(string $backupId): array
    {
        $database = $this->isolatedDatabase();
        $manifest = $this->resolveManifest($backupId);
        $dumpPath = $this->resolveDumpPath($manifest);

        $credentials = $this->writeCredentialsFile();
        $tableCount = 0;
        $migrationCount = 0;

        try {
            $this->recreateDatabase($database, $credentials);
            $this->loadDump($dumpPath, $database, $credentials);

            $tableCount = $this->countTables($database, $credentials);
            $migrationCount = $this->countMigrations($database, $credentials);

            $this->smokeTest($database, $credentials, $manifest, $tableCount, $migrationCount);
        } finally {
            try {
                $this->dropDatabase($database, $credentials);
            } finally {
                @unlink($credentials);
            }
        }

        return [
            'database' => $database,
            'table_count' => $tableCount,
            'migration_count' => $migrationCount,
        ];
    }

    private function resolveManifest(string $backupId): BackupManifest
    {
        $manifest = $this->backups->find($backupId);

        if ($manifest === null) {
            throw new RuntimeException("Backup [{$backupId}] was not found.");
        }

        if ($manifest->hashDb === null || ! isset($manifest->artifacts['db'])) {
            throw new RuntimeException("Backup [{$backupId}] does not contain a database dump.");
        }

        return $manifest;
    }

    /**
     * Verify the dump is present and its sha256 matches the manifest before any
     * database is created (contract section 6.3 integrity check).
     */
    private function resolveDumpPath(BackupManifest $manifest): string
    {
        $dumpPath = $this->storage->get($manifest->artifacts['db']);

        if (! is_file($dumpPath)) {
            throw new RuntimeException("Backup dump [{$manifest->artifacts['db']}] is missing.");
        }

        $actual = hash_file('sha256', $dumpPath);

        if ($actual === false || ! hash_equals((string) $manifest->hashDb, $actual)) {
            throw new RuntimeException('Database dump integrity check failed (hash_db mismatch).');
        }

        return $dumpPath;
    }

    private function isolatedDatabase(): string
    {
        $database = (string) config('backup.restore_test.database', '');

        if ($database === '') {
            throw new RuntimeException(
                'The isolated restore-test database is not configured '
                .'(config backup.restore_test.database / BACKUP_RESTORE_TEST_DATABASE).',
            );
        }

        if ($database === $this->workingDatabase()) {
            throw new RuntimeException(
                "Refusing to run: the restore-test database [{$database}] is the "
                .'working database. Configure a separate BACKUP_RESTORE_TEST_DATABASE.',
            );
        }

        return $database;
    }

    private function recreateDatabase(string $database, string $credentials): void
    {
        $this->runMysql($credentials, [
            '-e',
            "DROP DATABASE IF EXISTS `{$database}`; "
            ."CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
        ]);
    }

    private function loadDump(string $dumpPath, string $database, string $credentials): void
    {
        $temporarySql = $dumpPath.'.restore-test.sql';
        $this->decompress($dumpPath, $temporarySql);

        try {
            $handle = fopen($temporarySql, 'rb');

            if ($handle === false) {
                throw new RuntimeException("Unable to read the temporary dump at [{$temporarySql}].");
            }

            try {
                $binary = $this->mysqlBinary();

                Process::timeout(self::DEFAULT_TIMEOUT)
                    ->input($handle)
                    ->run([$binary, '--defaults-extra-file='.$credentials, $database])
                    ->throw();
            } finally {
                fclose($handle);
            }
        } finally {
            @unlink($temporarySql);
        }
    }

    private function countTables(string $database, string $credentials): int
    {
        $sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$database}';";

        return (int) trim($this->runMysql($credentials, ['-N', '-B', '-e', $sql]));
    }

    private function countMigrations(string $database, string $credentials): int
    {
        return (int) trim($this->runMysql($credentials, ['-N', '-B', '-e', 'SELECT COUNT(*) FROM migrations;', $database]));
    }

    private function smokeTest(
        string $database,
        string $credentials,
        BackupManifest $manifest,
        int $tableCount,
        int $migrationCount,
    ): void {
        if ($tableCount < 1) {
            throw new RuntimeException('Smoke test failed: no tables were restored.');
        }

        if ($migrationCount < 1) {
            throw new RuntimeException('Smoke test failed: the migrations table is empty after restore.');
        }

        if ($manifest->dbSchemaVersion === '') {
            return;
        }

        $sql = "SELECT COUNT(*) FROM migrations WHERE migration = '{$manifest->dbSchemaVersion}';";
        $found = (int) trim($this->runMysql($credentials, ['-N', '-B', '-e', $sql, $database]));

        if ($found < 1) {
            throw new RuntimeException(
                "Smoke test failed: schema version [{$manifest->dbSchemaVersion}] is missing after restore.",
            );
        }
    }

    private function dropDatabase(string $database, string $credentials): void
    {
        $this->runMysql($credentials, ['-e', "DROP DATABASE IF EXISTS `{$database}`;"]);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function runMysql(string $credentials, array $arguments): string
    {
        $binary = $this->mysqlBinary();

        $result = Process::timeout(self::DEFAULT_TIMEOUT)
            ->run([$binary, '--defaults-extra-file='.$credentials, ...$arguments])
            ->throw();

        return $result->output();
    }

    private function mysqlBinary(): string
    {
        $binary = $this->resolveBinary('mysql');

        if ($binary === null) {
            throw new RuntimeException(
                'mysql client was not found. In development install it with '
                .'`default-mysql-client` in docker/php/Dockerfile; in production '
                .'verify the binary against spec section 7.',
            );
        }

        return $binary;
    }

    private function resolveBinary(string $key): ?string
    {
        $binary = (string) config("backup.binaries.{$key}", '');

        if ($binary === '') {
            return null;
        }

        if (str_contains($binary, '/') || str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_executable($binary) ? $binary : null;
        }

        return (new ExecutableFinder)->find($binary);
    }

    private function workingDatabase(): string
    {
        $connection = (string) config('database.default');

        return (string) config("database.connections.{$connection}.database", '');
    }

    private function writeCredentialsFile(): string
    {
        $connection = (string) config('database.default');

        $lines = [
            '[client]',
            'host='.$this->escapeOption((string) config("database.connections.{$connection}.host", '')),
            'port='.$this->escapeOption((string) config("database.connections.{$connection}.port", '')),
            'user='.$this->escapeOption((string) config('backup.restore_test.username', 'root')),
            'password='.$this->escapeOption((string) config('backup.restore_test.password', '')),
        ];

        $path = tempnam(sys_get_temp_dir(), 'ffxi-restore-test-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary credentials file.');
        }

        file_put_contents($path, implode("\n", $lines)."\n");
        chmod($path, 0600);

        return $path;
    }

    /**
     * Escape a value for a MySQL option file (`[client]` group).
     */
    private function escapeOption(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function decompress(string $source, string $destination): void
    {
        $input = gzopen($source, 'rb');

        if ($input === false) {
            throw new RuntimeException("Unable to read the dump at [{$source}].");
        }

        $output = fopen($destination, 'wb');

        if ($output === false) {
            gzclose($input);

            throw new RuntimeException("Unable to open [{$destination}] for writing.");
        }

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1 << 20);

                if ($chunk === false) {
                    throw new RuntimeException('Failed while decompressing the dump.');
                }

                fwrite($output, $chunk);
            }
        } catch (Throwable $exception) {
            fclose($output);
            gzclose($input);

            throw $exception;
        }

        fclose($output);
        gzclose($input);
    }
}
