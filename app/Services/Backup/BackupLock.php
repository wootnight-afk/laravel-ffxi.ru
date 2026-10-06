<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;

/**
 * File lock that serialises backup and restore operations
 * (ADR-004 section 3.6, spec section 10).
 *
 * Uses a non-blocking `flock` when available and falls back to a mkdir lock
 * on hosts without the extension, mirroring the queue lock strategy.
 */
class BackupLock
{
    private const DEFAULT_TTL = 3600;

    /** @var array<string, resource> */
    private array $handles = [];

    /** @var array<string, string> */
    private array $directoryLocks = [];

    public function __construct(
        private readonly ?bool $useFlock = null,
    ) {}

    /**
     * Try to take the lock without blocking.
     *
     * Returns false when the lock is already held by another process (or
     * instance) and never waits.
     */
    public function acquire(string $key = 'backup', int $ttlSeconds = self::DEFAULT_TTL): bool
    {
        if (isset($this->handles[$key]) || isset($this->directoryLocks[$key])) {
            return true;
        }

        $path = $this->pathFor($key);

        if ($this->flockEnabled()) {
            File::ensureDirectoryExists(dirname($path));

            $handle = fopen($path, 'c');

            if ($handle === false) {
                return false;
            }

            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);

                return false;
            }

            $this->handles[$key] = $handle;

            return true;
        }

        return $this->acquireDirectoryLock($key, $path.'.d', $ttlSeconds);
    }

    public function release(string $key = 'backup'): void
    {
        if (isset($this->handles[$key])) {
            flock($this->handles[$key], LOCK_UN);
            fclose($this->handles[$key]);
            unset($this->handles[$key]);

            return;
        }

        if (isset($this->directoryLocks[$key])) {
            @rmdir($this->directoryLocks[$key]);
            unset($this->directoryLocks[$key]);
        }
    }

    public function isHeld(string $key = 'backup'): bool
    {
        if (isset($this->handles[$key]) || isset($this->directoryLocks[$key])) {
            return true;
        }

        $path = $this->pathFor($key);

        if ($this->flockEnabled()) {
            if (! is_file($path)) {
                return false;
            }

            $handle = fopen($path, 'c');

            if ($handle === false) {
                return false;
            }

            $held = ! flock($handle, LOCK_EX | LOCK_NB);

            if (! $held) {
                flock($handle, LOCK_UN);
            }

            fclose($handle);

            return $held;
        }

        return is_dir($path.'.d');
    }

    private function acquireDirectoryLock(string $key, string $directory, int $ttlSeconds): bool
    {
        File::ensureDirectoryExists(dirname($directory));

        if (@mkdir($directory, 0700)) {
            $this->directoryLocks[$key] = $directory;

            return true;
        }

        $modifiedAt = @filemtime($directory);

        if ($modifiedAt !== false && (time() - $modifiedAt) > $ttlSeconds) {
            @rmdir($directory);

            if (@mkdir($directory, 0700)) {
                $this->directoryLocks[$key] = $directory;

                return true;
            }
        }

        return false;
    }

    private function flockEnabled(): bool
    {
        return $this->useFlock ?? function_exists('flock');
    }

    private function pathFor(string $key): string
    {
        $base = (string) config('backup.lock_path');

        return $key === 'backup' ? $base : $base.'.'.$key;
    }
}
