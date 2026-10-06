<?php

use App\Services\Backup\BackupLock;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->lockDir = sys_get_temp_dir().'/ffxi-lock-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->lockDir);
    config(['backup.lock_path' => $this->lockDir.'/backup.lock']);
});

afterEach(function () {
    File::deleteDirectory($this->lockDir);
});

it('acquires and releases a flock', function () {
    $lock = new BackupLock;

    expect($lock->acquire('backup'))->toBeTrue();
    expect($lock->isHeld('backup'))->toBeTrue();

    $lock->release('backup');

    expect($lock->isHeld('backup'))->toBeFalse();
});

it('reports the lock as held while another instance owns it', function () {
    $holder = new BackupLock;
    $other = new BackupLock;

    expect($holder->acquire('backup'))->toBeTrue();
    expect($other->isHeld('backup'))->toBeTrue();
});

it('refuses a second non-blocking acquire from another instance', function () {
    $holder = new BackupLock;
    $other = new BackupLock;

    expect($holder->acquire('backup'))->toBeTrue();
    expect($other->acquire('backup'))->toBeFalse();

    $holder->release('backup');

    expect($other->acquire('backup'))->toBeTrue();
    $other->release('backup');
});

it('is idempotent when the same instance acquires twice', function () {
    $lock = new BackupLock;

    expect($lock->acquire('backup'))->toBeTrue();
    expect($lock->acquire('backup'))->toBeTrue();
});

it('falls back to a directory lock when flock is unavailable', function () {
    $holder = new BackupLock(useFlock: false);
    $other = new BackupLock(useFlock: false);

    expect($holder->acquire('backup'))->toBeTrue();
    expect(is_dir($this->lockDir.'/backup.lock.d'))->toBeTrue();
    expect($holder->isHeld('backup'))->toBeTrue();
    expect($other->acquire('backup'))->toBeFalse();

    $holder->release('backup');

    expect($holder->isHeld('backup'))->toBeFalse();
    expect(is_dir($this->lockDir.'/backup.lock.d'))->toBeFalse();
});

it('breaks a stale directory lock past its ttl', function () {
    $directory = $this->lockDir.'/backup.lock.d';
    File::ensureDirectoryExists($directory);
    touch($directory, time() - 7200);

    $lock = new BackupLock(useFlock: false);

    expect($lock->acquire('backup', ttlSeconds: 3600))->toBeTrue();
    $lock->release('backup');
});
