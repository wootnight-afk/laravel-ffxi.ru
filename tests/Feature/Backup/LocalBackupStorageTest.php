<?php

use App\Services\Backup\LocalBackupStorage;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->storageDir = sys_get_temp_dir().'/ffxi-storage-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->storageDir);
    config(['backup.path' => $this->storageDir]);

    $this->storage = new LocalBackupStorage;
});

afterEach(function () {
    File::deleteDirectory($this->storageDir);
});

it('stores a file and reports it as existing', function () {
    $source = $this->storageDir.'/source.bin';
    file_put_contents($source, 'payload');

    $this->storage->put('2026/10/artifact.bin', $source);

    expect($this->storage->exists('2026/10/artifact.bin'))->toBeTrue();
    expect(file_get_contents($this->storage->get('2026/10/artifact.bin')))->toBe('payload');
});

it('returns an absolute path inside the configured base directory', function () {
    expect($this->storage->get('2026/10/artifact.bin'))
        ->toBe($this->storageDir.'/2026/10/artifact.bin');
});

it('reports the size of a stored artifact', function () {
    $source = $this->storageDir.'/source.bin';
    file_put_contents($source, str_repeat('x', 128));

    $this->storage->put('artifact.bin', $source);

    expect($this->storage->size('artifact.bin'))->toBe(128);
});

it('throws when reading the size of a missing artifact', function () {
    expect(fn () => $this->storage->size('missing.bin'))
        ->toThrow(RuntimeException::class);
});

it('deletes a stored artifact', function () {
    $source = $this->storageDir.'/source.bin';
    file_put_contents($source, 'payload');

    $this->storage->put('artifact.bin', $source);
    $this->storage->delete('artifact.bin');

    expect($this->storage->exists('artifact.bin'))->toBeFalse();
});

it('returns an empty list when nothing is stored', function () {
    expect($this->storage->list())->toBe([]);
});

it('lists decoded manifests only', function () {
    $manifest = ['backup_id' => 'abc', 'type' => 'full'];

    $manifestSource = $this->storageDir.'/manifest.json';
    file_put_contents($manifestSource, json_encode($manifest));
    $this->storage->put('2026/10/abc.manifest.json', $manifestSource);

    $noiseSource = $this->storageDir.'/noise.bin';
    file_put_contents($noiseSource, 'not a manifest');
    $this->storage->put('2026/10/abc.dump.gz', $noiseSource);

    $listed = $this->storage->list();

    expect($listed)->toHaveCount(1);
    expect($listed[0]['backup_id'])->toBe('abc');
    expect($listed[0]['type'])->toBe('full');
});
