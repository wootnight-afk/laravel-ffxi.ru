<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Contracts\BackupStorage;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Filesystem-backed BackupStorage rooted at `config('backup.path')`.
 *
 * Uses the plain filesystem instead of the Storage facade on purpose: the
 * backup directory is deliberately not a Laravel disk and must stay outside
 * the web root.
 */
class LocalBackupStorage implements BackupStorage
{
    private const MANIFEST_SUFFIX = '.manifest.json';

    public function put(string $relativePath, string $sourcePath): void
    {
        $destination = $this->get($relativePath);

        File::ensureDirectoryExists(dirname($destination));

        if (! copy($sourcePath, $destination)) {
            throw new RuntimeException("Unable to copy [{$sourcePath}] to [{$destination}].");
        }

        chmod($destination, 0600);
    }

    public function get(string $relativePath): string
    {
        return $this->basePath().DIRECTORY_SEPARATOR.ltrim($relativePath, '/');
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->get($relativePath));
    }

    public function delete(string $relativePath): void
    {
        $path = $this->get($relativePath);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function size(string $relativePath): int
    {
        $path = $this->get($relativePath);

        if (! is_file($path)) {
            throw new RuntimeException("Backup artifact [{$relativePath}] does not exist.");
        }

        $size = filesize($path);

        if ($size === false) {
            throw new RuntimeException("Unable to read the size of [{$relativePath}].");
        }

        return $size;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $base = $this->basePath();

        if (! is_dir($base)) {
            return [];
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), self::MANIFEST_SUFFIX)) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        $manifests = [];

        foreach ($paths as $path) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (is_array($decoded)) {
                $manifests[] = $decoded;
            }
        }

        return $manifests;
    }

    private function basePath(): string
    {
        return (string) config('backup.path');
    }
}
