<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Produces a gzip-compressed MySQL dump via `mysqldump` (ADR-004 section 3.3).
 *
 * Credentials are passed through a temporary `--defaults-extra-file` (mode
 * 0600) so the password never appears in the process list. The dump is written
 * to a temporary SQL file and then compressed with a streaming gzip writer, so
 * the archive stays a valid `.dump.gz` without depending on the gzip binary.
 */
class DatabaseDumper
{
    private const DEFAULT_TIMEOUT = 1800;

    public function __construct(
        private readonly ?string $binary = null,
    ) {}

    /**
     * Whether the configured `mysqldump` binary can be executed.
     */
    public function available(): bool
    {
        return $this->resolveBinary() !== null;
    }

    /**
     * Dump the current connection's database to `$outputPath`.
     *
     * @return array{path: string, hash: string}
     */
    public function dump(string $outputPath): array
    {
        $binary = $this->resolveBinary();

        if ($binary === null) {
            throw new RuntimeException(
                'mysqldump was not found. In development install it with '
                .'`default-mysql-client` in docker/php/Dockerfile; in production '
                .'verify the binary against spec section 7.',
            );
        }

        $temporarySql = $outputPath.'.sql';
        $credentials = $this->writeCredentialsFile();

        try {
            $command = [
                $binary,
                '--defaults-extra-file='.$credentials,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--result-file='.$temporarySql,
                $this->databaseName(),
            ];

            Process::timeout(self::DEFAULT_TIMEOUT)->run($command)->throw();

            $this->compress($temporarySql, $outputPath);
        } finally {
            @unlink($credentials);
            @unlink($temporarySql);
        }

        $hash = hash_file('sha256', $outputPath);

        if ($hash === false) {
            throw new RuntimeException("Unable to hash the dump at [{$outputPath}].");
        }

        return ['path' => $outputPath, 'hash' => $hash];
    }

    /**
     * Resolve the mysqldump executable, honouring an explicit binary path.
     */
    private function resolveBinary(): ?string
    {
        $binary = $this->binary ?? (string) config('backup.binaries.mysqldump', 'mysqldump');

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

        $path = tempnam(sys_get_temp_dir(), 'ffxi-db-');

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

    private function compress(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');

        if ($input === false) {
            throw new RuntimeException("Unable to read the temporary dump at [{$source}].");
        }

        $output = gzopen($destination, 'wb9');

        if ($output === false) {
            fclose($input);

            throw new RuntimeException("Unable to open [{$destination}] for writing.");
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1 << 20);

                if ($chunk === false) {
                    throw new RuntimeException('Failed while reading the temporary dump.');
                }

                gzwrite($output, $chunk);
            }
        } catch (Throwable $exception) {
            gzclose($output);
            fclose($input);

            throw $exception;
        }

        gzclose($output);
        fclose($input);
    }
}
