<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Contracts\BackupStorage;
use App\Models\RestoreRequest;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Executes the restore flow from STAGE-9-CONTRACT section 6.
 *
 * Read-only checks ({@see self::check}) and the destructive apply
 * ({@see self::apply}) both live here. It is never called from HTTP: the
 * Filament page only creates a {@see RestoreRequest}, and a CLI command
 * consumes it (ADR-004 R4).
 */
class RestoreService
{
    private const DEFAULT_TIMEOUT = 1800;

    public function __construct(
        private readonly BackupService $backups,
        private readonly BackupStorage $storage,
        private readonly BackupLock $lock,
        private readonly AuditLogger $audit,
        private readonly RestoreRequestService $requests,
    ) {}

    /**
     * Read-only validation (contract section 6.3-6.4).
     *
     * @return array<int, string> Human-readable problems; empty means OK.
     */
    public function check(RestoreRequest $request): array
    {
        $problems = [];

        if (! $request->isPending()) {
            $problems[] = "Request status is [{$request->status}], expected [pending].";
        }

        if ($request->isExpired()) {
            $problems[] = 'Request expired at '.$request->expires_at->toIso8601String().'.';
        }

        if (! $this->requests->tokenMatches($request)) {
            $problems[] = 'Request token is invalid (HMAC mismatch).';
        }

        $manifest = $this->backups->find($request->backup_id);

        if ($manifest === null) {
            $problems[] = "Backup [{$request->backup_id}] was not found.";

            return $problems;
        }

        foreach ($manifest->artifacts as $kind => $relativePath) {
            if (! $this->storage->exists($relativePath)) {
                $problems[] = "Backup artifact [{$kind}] is missing.";
            }
        }

        if ($manifest->hashDb !== null && isset($manifest->artifacts['db'])) {
            $dumpPath = $this->storage->get($manifest->artifacts['db']);
            $actual = is_file($dumpPath) ? hash_file('sha256', $dumpPath) : false;

            if ($actual === false || ! hash_equals($manifest->hashDb, $actual)) {
                $problems[] = 'Database dump integrity check failed (hash_db mismatch).';
            }
        }

        return array_merge($problems, $this->compatibilityProblems($manifest));
    }

    /**
     * Apply a restore request (contract section 6.5).
     *
     * @throws RuntimeException when a pre-check fails or the restore fails.
     */
    public function apply(RestoreRequest $request): void
    {
        $problems = $this->check($request);

        if ($problems !== []) {
            $this->fail($request, $problems);

            throw new RuntimeException('Restore pre-checks failed: '.implode(' ', $problems));
        }

        if (! $this->lock->acquire('backup')) {
            $message = 'Another backup or restore is already running.';
            $this->fail($request, [$message]);

            throw new RuntimeException($message);
        }

        $restoreStarted = false;

        try {
            $manifest = $this->backups->find($request->backup_id);

            if ($manifest === null) {
                throw new RuntimeException("Backup [{$request->backup_id}] was not found.");
            }

            // Step 3: auto-backup of the current state before any mutation.
            // The backup lock is already held, so the service must not re-acquire
            // it (contract section 6.5 step 3).
            $this->backups->create(
                BackupManifest::TYPE_FULL,
                BackupManifest::SCOPE_STORAGE_APP,
                'admin:'.$request->admin_user_id,
                (int) $request->admin_user_id,
                acquireLock: false,
            );

            // Step 4: put the site into maintenance.
            app()->maintenanceMode()->activate([]);
            $restoreStarted = true;

            // Step 5-6: database first, then files (fixed order).
            $this->restoreDatabase($manifest);
            $this->restoreFiles($manifest);

            // Step 7: back online.
            app()->maintenanceMode()->deactivate();

            // Step 8: smoke test.
            $this->smokeTest();

            // Step 9-10: audit and consume the request.
            $request->forceFill([
                'status' => RestoreRequest::STATUS_APPLIED,
                'consumed_at' => Carbon::now(),
            ])->save();

            // Security event: IP is recorded (contract section 12).
            $this->audit->log(
                action: 'restore.applied',
                new: [
                    'request_id' => $request->id,
                    'backup_id' => $request->backup_id,
                    'admin_user_id' => (int) $request->admin_user_id,
                ],
            );
        } catch (Throwable $exception) {
            if ($restoreStarted) {
                // The site stays down for manual intervention; no automatic
                // rollback is attempted (contract section 6.6).
                app()->maintenanceMode()->activate([]);
            }

            $this->fail($request, [$exception->getMessage()]);

            throw $exception;
        } finally {
            $this->lock->release('backup');
        }
    }

    /**
     * @return array<int, string>
     */
    private function compatibilityProblems(BackupManifest $manifest): array
    {
        $problems = [];

        // 1. php_version available: the restored code expects the same PHP
        //    major.minor as the host runs.
        $backupPhp = $this->majorMinor($manifest->phpVersion);
        $currentPhp = $this->majorMinor(PHP_VERSION);

        if ($backupPhp !== null && $currentPhp !== null && $backupPhp !== $currentPhp) {
            $problems[] = "PHP version mismatch: backup was created on {$manifest->phpVersion}, host runs ".PHP_VERSION.'.';
        }

        // 2. laravel_version supported: a rollback never crosses a framework
        //    major version (that is a migration, not a rollback).
        $backupLaravel = $this->major($manifest->laravelVersion);
        $currentLaravel = $this->major(app()->version());

        if ($backupLaravel !== null && $currentLaravel !== null && $backupLaravel !== $currentLaravel) {
            $problems[] = "Laravel major version mismatch: backup {$manifest->laravelVersion} vs host {$currentLaravel}.";
        }

        // 3. every required extension must be loaded.
        $missing = [];

        foreach ($manifest->extensions as $extension => $required) {
            if ($required && ! extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        if ($missing !== []) {
            $problems[] = 'Missing PHP extensions: '.implode(', ', $missing).'.';
        }

        // 4. db_schema_version compatible: the backup schema must be known to
        //    the current codebase (already applied), never a future schema.
        if ($manifest->dbSchemaVersion !== ''
            && ! DB::table('migrations')->where('migration', $manifest->dbSchemaVersion)->exists()
        ) {
            $problems[] = "Database schema version [{$manifest->dbSchemaVersion}] is not known to the codebase.";
        }

        return $problems;
    }

    private function restoreDatabase(BackupManifest $manifest): void
    {
        if ($manifest->hashDb === null || ! isset($manifest->artifacts['db'])) {
            return;
        }

        $binary = $this->resolveBinary('mysql');

        if ($binary === null) {
            throw new RuntimeException(
                'mysql client was not found. In development install it with '
                .'`default-mysql-client` in docker/php/Dockerfile; in production '
                .'verify the binary against spec section 7.',
            );
        }

        $dumpPath = $this->storage->get($manifest->artifacts['db']);
        $temporarySql = $dumpPath.'.restore.sql';
        $credentials = $this->writeCredentialsFile();

        $this->decompress($dumpPath, $temporarySql);

        try {
            $handle = fopen($temporarySql, 'rb');

            if ($handle === false) {
                throw new RuntimeException("Unable to read the temporary dump at [{$temporarySql}].");
            }

            try {
                Process::timeout(self::DEFAULT_TIMEOUT)
                    ->input($handle)
                    ->run([
                        $binary,
                        '--defaults-extra-file='.$credentials,
                        $this->databaseName(),
                    ])
                    ->throw();
            } finally {
                fclose($handle);
            }
        } finally {
            @unlink($temporarySql);
            @unlink($credentials);
        }
    }

    private function restoreFiles(BackupManifest $manifest): void
    {
        if (! isset($manifest->artifacts['files'])) {
            return;
        }

        $binary = $this->resolveBinary('tar');

        if ($binary === null) {
            throw new RuntimeException('tar was not found. Verify the binary against spec section 7.');
        }

        $archivePath = $this->storage->get($manifest->artifacts['files']);

        Process::timeout(self::DEFAULT_TIMEOUT)
            ->run([$binary, '-xzf', $archivePath, '-C', base_path()])
            ->throw();
    }

    /**
     * Minimal post-restore smoke test: the restored database must answer.
     * External uptime checks cover the `/up` endpoint (spec section 18).
     */
    private function smokeTest(): void
    {
        DB::select('SELECT 1');

        if (! Schema::hasTable('migrations')) {
            throw new RuntimeException('Smoke test failed: the migrations table is missing after restore.');
        }
    }

    /**
     * @param  array<int, string>  $problems
     */
    private function fail(RestoreRequest $request, array $problems): void
    {
        if ($request->isPending()) {
            $request->forceFill(['status' => RestoreRequest::STATUS_FAILED])->save();
        }

        // Security event: IP is recorded (contract section 12).
        $this->audit->log(
            action: 'restore.failed',
            new: [
                'request_id' => $request->id,
                'backup_id' => $request->backup_id,
                'problems' => $problems,
            ],
        );
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
        } finally {
            fclose($output);
            gzclose($input);
        }
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

    private function databaseName(): string
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
            'user='.$this->escapeOption((string) config("database.connections.{$connection}.username", '')),
            'password='.$this->escapeOption((string) config("database.connections.{$connection}.password", '')),
        ];

        $path = tempnam(sys_get_temp_dir(), 'ffxi-restore-');

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

    private function majorMinor(string $version): ?string
    {
        if (preg_match('/^v?(\d+)\.(\d+)/', $version, $matches) !== 1) {
            return null;
        }

        return $matches[1].'.'.$matches[2];
    }

    private function major(string $version): ?string
    {
        if (preg_match('/^v?(\d+)/', $version, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
