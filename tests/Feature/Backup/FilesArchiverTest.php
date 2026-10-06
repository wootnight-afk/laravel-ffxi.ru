<?php

use App\Services\Backup\BackupManifest;
use App\Services\Backup\FilesArchiver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * @return array<int, string>
 */
function archiveTarEntries(string $archivePath): array
{
    $result = Process::run(['tar', '-tzf', $archivePath]);
    $result->throw();

    $entries = array_filter(array_map('trim', explode("\n", trim($result->output()))));

    return array_values(array_map(static fn (string $entry): string => rtrim($entry, '/'), $entries));
}

beforeEach(function () {
    $this->workDir = sys_get_temp_dir().'/ffxi-archive-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->workDir);

    $this->marker = storage_path('app/test/e9-1-marker.txt');
    File::ensureDirectoryExists(dirname($this->marker));
    file_put_contents($this->marker, 'e9-1 marker');

    // lang/ must be part of Scope B (ADR-004 section 3.1, E9.2 amendment).
    // Create a fixture so the assertion holds even on a checkout without
    // translations, and remove it afterwards to keep the tree clean.
    $this->langDir = base_path('lang');
    $this->langFixture = base_path('lang/ru/e9-2-fixture.php');
    $this->langDirCreated = ! is_dir($this->langDir);
    File::ensureDirectoryExists(dirname($this->langFixture));
    file_put_contents($this->langFixture, "<?php\n\nreturn [];\n");

    $this->archiver = new FilesArchiver;
});

afterEach(function () {
    File::deleteDirectory($this->workDir);
    File::delete($this->marker);
    File::delete($this->langFixture);

    if ($this->langDirCreated && is_dir($this->langDir)) {
        File::deleteDirectory($this->langDir);
    }
});

it('detects that tar is available', function () {
    expect($this->archiver->available())->toBeTrue();
});

it('archives storage/app for scope storage_app', function () {
    $output = $this->workDir.'/storage_app.tar.gz';

    $result = $this->archiver->archive(BackupManifest::SCOPE_STORAGE_APP, $output);

    expect($result['path'])->toBe($output);
    expect($result['hash'])->toBe(hash_file('sha256', $output));
    expect($result['files'])->toBe(['storage/app']);

    $entries = archiveTarEntries($output);

    expect($entries)->toContain('storage/app/test/e9-1-marker.txt');
    expect($entries)->not->toContain('app/Http/Kernel.php');
    expect($entries)->not->toContain('composer.json');
});

it('archives the exact scope B composition for whole_site', function () {
    $output = $this->workDir.'/whole_site.tar.gz';

    $result = $this->archiver->archive(BackupManifest::SCOPE_WHOLE_SITE, $output);

    expect($result['files'])->toContain('app', 'config', 'storage/app', 'composer.json', 'composer.lock');
    expect($result['files'])->toContain('lang');

    $entries = archiveTarEntries($output);
    $flattened = implode("\n", $entries);

    expect($entries)->toContain('app', 'config', 'composer.json', 'composer.lock');
    expect($entries)->toContain('storage/app/test/e9-1-marker.txt');
    expect($entries)->toContain('lang', 'lang/ru/e9-2-fixture.php');

    foreach (['vendor', 'node_modules', '.git', 'docker', 'docs', 'tests', '.github', '.vscode', '.idea'] as $excluded) {
        expect($flattened)->not->toMatch('#(^|\n)'.preg_quote($excluded, '#').'/#');
    }

    expect($flattened)->not->toMatch('#(^|\n)storage/(framework|logs|backups)/#');
    expect($flattened)->not->toMatch('#(^|\n)bootstrap/cache/.*\.php$#m');
});

it('skips optional files that do not exist', function () {
    $paths = $this->archiver->resolveIncludePaths(BackupManifest::SCOPE_WHOLE_SITE);

    expect($paths)->toContain('vite.config.js');
    expect($paths)->not->toContain('vite.config.ts');
    expect($paths)->not->toContain('phpstan.neon.dist');
});

it('rejects an unknown scope', function () {
    expect(fn () => $this->archiver->archive('partial', $this->workDir.'/x.tar.gz'))
        ->toThrow(RuntimeException::class, 'Unknown backup scope');
});
