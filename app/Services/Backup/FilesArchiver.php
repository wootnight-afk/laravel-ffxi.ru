<?php

declare(strict_types=1);

namespace App\Services\Backup;

use FilesystemIterator;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Builds a gzip-compressed tar archive for the two Stage 9 scopes
 * (ADR-004 section 3).
 *
 * Scope A (`storage_app`) archives `storage/app`. Scope B (`whole_site`)
 * archives the exact, explicit list from ADR-004 section 3 and never relies on
 * a catch-all phrase. Missing optional files are skipped gracefully.
 */
class FilesArchiver
{
    private const DEFAULT_TIMEOUT = 1800;

    /**
     * Scope A include list.
     *
     * @var array<int, string>
     */
    private const STORAGE_APP_PATHS = [
        'storage/app',
    ];

    /**
     * Scope B include candidates (ADR-004 section 3.1 and 3.2). Optional
     * variants are listed together; only existing entries are archived.
     *
     * @var array<int, string>
     */
    private const WHOLE_SITE_PATHS = [
        'app',
        'bootstrap',
        'config',
        'database',
        'public',
        'resources',
        'routes',
        'lang',
        'storage/app',
        'artisan',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'phpunit.xml',
        'phpstan.neon',
        'phpstan.neon.dist',
        'vite.config.js',
        'vite.config.ts',
        '.editorconfig',
        '.gitattributes',
        '.gitignore',
        '.env.example',
        'Makefile',
        'CHANGELOG.md',
        'KODA.md',
        '.env',
    ];

    /**
     * Scope B exclude list (ADR-004 section 3.3). Never shortened.
     *
     * @var array<int, string>
     */
    private const EXCLUDE_PATTERNS = [
        'vendor',
        'node_modules',
        '.git',
        'docker',
        'docs',
        'tests',
        '.github',
        '.vscode',
        '.idea',
        '.fleet',
        '.kodarules',
        'storage/logs',
        'storage/framework',
        'storage/backups',
        'public/storage',
        'public/hot',
        'bootstrap/cache/*.php',
    ];

    public function __construct(
        private readonly ?string $binary = null,
    ) {}

    public function available(): bool
    {
        return $this->resolveBinary() !== null;
    }

    /**
     * Archive the given scope to `$outputPath`.
     *
     * @return array{path: string, hash: string, files: array<int, string>}
     */
    public function archive(string $scope, string $outputPath): array
    {
        $binary = $this->resolveBinary();

        if ($binary === null) {
            throw new RuntimeException(
                'tar was not found. Verify the binary against spec section 7.',
            );
        }

        $files = $this->resolveIncludePaths($scope);

        if ($files === []) {
            throw new RuntimeException("Nothing to archive for scope [{$scope}].");
        }

        $listFile = $this->writeListFile($files);

        try {
            $command = [$binary, '-czf', $outputPath];

            foreach (self::EXCLUDE_PATTERNS as $pattern) {
                $command[] = '--exclude='.$pattern;
            }

            $command[] = '-C';
            $command[] = base_path();
            $command[] = '-T';
            $command[] = $listFile;

            Process::timeout(self::DEFAULT_TIMEOUT)->run($command)->throw();
        } finally {
            @unlink($listFile);
        }

        $hash = hash_file('sha256', $outputPath);

        if ($hash === false) {
            throw new RuntimeException("Unable to hash the archive at [{$outputPath}].");
        }

        return ['path' => $outputPath, 'hash' => $hash, 'files' => $files];
    }

    /**
     * Resolve the include list for a scope, dropping entries that do not exist.
     *
     * @return array<int, string>
     */
    public function resolveIncludePaths(string $scope): array
    {
        $candidates = match ($scope) {
            BackupManifest::SCOPE_STORAGE_APP => self::STORAGE_APP_PATHS,
            BackupManifest::SCOPE_WHOLE_SITE => self::WHOLE_SITE_PATHS,
            default => throw new RuntimeException("Unknown backup scope [{$scope}]."),
        };

        $resolved = [];

        foreach ($candidates as $candidate) {
            if (file_exists(base_path($candidate))) {
                $resolved[] = $candidate;
            }
        }

        return $resolved;
    }

    /**
     * Estimate the on-disk size (bytes) of the files that would be archived
     * for a scope, honouring the exclude list. Used by the free-space safety
     * check (ADR-004 section 5.2).
     */
    public function estimateSize(string $scope): int
    {
        $total = 0;

        foreach ($this->resolveIncludePaths($scope) as $candidate) {
            $absolute = base_path($candidate);

            if (is_file($absolute)) {
                if (! $this->isExcluded($candidate)) {
                    $total += $this->fileSize($absolute);
                }

                continue;
            }

            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                if ($this->isExcluded($this->relativePath($file->getPathname()))) {
                    continue;
                }

                $total += $this->fileSize($file->getPathname());
            }
        }

        return $total;
    }

    /**
     * @param  array<int, string>  $files
     */
    private function writeListFile(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ffxi-tar-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary tar list file.');
        }

        file_put_contents($path, implode("\n", $files)."\n");

        return $path;
    }

    /**
     * Relative, forward-slash path of an absolute path inside the project root.
     */
    private function relativePath(string $absolutePath): string
    {
        $relative = str_replace(base_path(), '', $absolutePath);
        $relative = ltrim($relative, DIRECTORY_SEPARATOR);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    private function isExcluded(string $relativePath): bool
    {
        foreach (self::EXCLUDE_PATTERNS as $pattern) {
            if (str_contains($pattern, '*')) {
                if (fnmatch($pattern, $relativePath)) {
                    return true;
                }

                continue;
            }

            if ($relativePath === $pattern || str_starts_with($relativePath, $pattern.'/')) {
                return true;
            }
        }

        return false;
    }

    private function fileSize(string $path): int
    {
        $size = @filesize($path);

        return $size === false ? 0 : $size;
    }

    private function resolveBinary(): ?string
    {
        $binary = $this->binary ?? (string) config('backup.binaries.tar', 'tar');

        if ($binary === '') {
            return null;
        }

        if (str_contains($binary, '/') || str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_executable($binary) ? $binary : null;
        }

        return (new ExecutableFinder)->find($binary);
    }
}
