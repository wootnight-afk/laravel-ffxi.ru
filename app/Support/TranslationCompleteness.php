<?php

declare(strict_types=1);

namespace App\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Compares translation keys between two locale directories (ADR-011 §2.16).
 *
 * Keys are flattened to `namespace.key.path`, where the namespace is the
 * file path relative to the locale directory (`admin/users.php` becomes
 * `admin.users`). Values are not compared — only key completeness.
 */
final class TranslationCompleteness
{
    /**
     * @return array{missing_in_target: array<int, string>, missing_in_reference: array<int, string>}
     */
    public function compare(string $langPath, string $reference = 'ru', string $target = 'en'): array
    {
        $referenceKeys = $this->keys($langPath.DIRECTORY_SEPARATOR.$reference);
        $targetKeys = $this->keys($langPath.DIRECTORY_SEPARATOR.$target);

        $missingInTarget = array_values(array_diff($referenceKeys, $targetKeys));
        $missingInReference = array_values(array_diff($targetKeys, $referenceKeys));

        sort($missingInTarget);
        sort($missingInReference);

        return [
            'missing_in_target' => $missingInTarget,
            'missing_in_reference' => $missingInReference,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function keys(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $keys = [];

        foreach ($this->files($directory) as $file) {
            $namespace = str_replace(
                DIRECTORY_SEPARATOR,
                '.',
                substr($file->getPathname(), strlen($directory) + 1, -4),
            );

            $translations = require $file->getPathname();

            if (! is_array($translations)) {
                continue;
            }

            foreach ($this->flatten($translations) as $key) {
                $keys[] = $namespace.'.'.$key;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param  array<array-key, mixed>  $translations
     * @return array<int, string>
     */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $keys = [];

        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $path));

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }

    /**
     * @return iterable<int, SplFileInfo>
     */
    private function files(string $directory): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
