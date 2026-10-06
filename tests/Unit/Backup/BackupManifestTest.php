<?php

use App\Services\Backup\BackupManifest;

/**
 * @param  array<string, mixed>  $overrides
 */
function backupManifestFixture(array $overrides = []): BackupManifest
{
    return BackupManifest::fromArray(array_merge([
        'format_version' => 1,
        'backup_id' => '3f2b1c4e-1111-2222-3333-444455556666',
        'type' => 'full',
        'scope' => 'storage_app',
        'is_complete' => true,
        'created_at' => '2026-10-06T04:00:00+00:00',
        'triggered_by' => 'cli',
        'app_version' => 'v0.9.0',
        'app_changelog_hash' => 'changelog-hash',
        'php_version' => '8.4.26',
        'laravel_version' => '13.33.0',
        'filament_version' => '5.8.4',
        'livewire_version' => '4.4.6',
        'composer_lock_hash' => 'composer-hash',
        'package_lock_hash' => 'package-hash',
        'db_schema_version' => '0001_01_01_000024_add_suspended_fields_to_users_table',
        'db_migrations' => ['0001_01_01_000001_create_users_table'],
        'extensions' => ['pdo_mysql' => true, 'gd' => false],
        'db_size_bytes' => 12345678,
        'files' => ['app', 'config', 'storage/app'],
        'hash_db' => 'db-hash',
        'hash_files' => 'files-hash',
        'created_by_user_id' => 1,
        'size_bytes' => 987654321,
        'artifacts' => [
            'db' => '2026/10/3f2b1c4e.dump.gz',
            'files' => '2026/10/3f2b1c4e.files.tar.gz',
        ],
    ], $overrides));
}

it('round-trips through toArray and fromArray', function () {
    $manifest = backupManifestFixture();

    $restored = BackupManifest::fromArray($manifest->toArray());

    expect($restored)->toEqual($manifest);
    expect($restored->toArray())->toBe($manifest->toArray());
});

it('round-trips through toJson and fromJson', function () {
    $manifest = backupManifestFixture();

    $restored = BackupManifest::fromJson($manifest->toJson());

    expect($restored)->toEqual($manifest);
});

it('preserves null scope and nullable fields through a round-trip', function () {
    $manifest = backupManifestFixture([
        'type' => 'db_only',
        'scope' => null,
        'is_complete' => false,
        'hash_files' => null,
        'files' => [],
        'created_by_user_id' => null,
        'app_version' => null,
    ]);

    $restored = BackupManifest::fromJson($manifest->toJson());

    expect($restored->scope)->toBeNull();
    expect($restored->hashFiles)->toBeNull();
    expect($restored->createdByUserId)->toBeNull();
    expect($restored->appVersion)->toBeNull();
});

it('accepts a valid db_only manifest', function () {
    $manifest = backupManifestFixture([
        'type' => 'db_only',
        'scope' => null,
        'is_complete' => false,
        'hash_files' => null,
        'files' => [],
    ]);

    expect(fn () => $manifest->validate())->not->toThrow(InvalidArgumentException::class);
});

it('rejects a db_only manifest that carries a scope', function () {
    $manifest = backupManifestFixture([
        'type' => 'db_only',
        'is_complete' => false,
        'hash_files' => null,
        'files' => [],
    ]);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'null scope');
});

it('rejects a db_only manifest marked complete', function () {
    $manifest = backupManifestFixture([
        'type' => 'db_only',
        'scope' => null,
        'is_complete' => true,
        'hash_files' => null,
        'files' => [],
    ]);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'not be marked complete');
});

it('rejects a db_only manifest with a files hash', function () {
    $manifest = backupManifestFixture([
        'type' => 'db_only',
        'scope' => null,
        'is_complete' => false,
        'files' => [],
    ]);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'hash_files');
});

it('rejects a db_only manifest that lists files', function () {
    $manifest = backupManifestFixture([
        'type' => 'db_only',
        'scope' => null,
        'is_complete' => false,
        'hash_files' => null,
    ]);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'list files');
});

it('rejects a full manifest without a scope', function () {
    $manifest = backupManifestFixture(['type' => 'full', 'scope' => null]);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'requires a scope');
});

it('rejects a site_only manifest without a scope', function () {
    $manifest = backupManifestFixture(['type' => 'site_only', 'scope' => null]);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'requires a scope');
});

it('rejects a full manifest that is not complete', function () {
    $manifest = backupManifestFixture(['type' => 'full', 'is_complete' => false]);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'marked complete');
});

it('rejects an unknown type', function () {
    $manifest = backupManifestFixture(['type' => 'incremental']);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'Unknown backup type');
});

it('rejects an unknown scope', function () {
    $manifest = backupManifestFixture(['scope' => 'partial']);

    expect(fn () => $manifest->validate())
        ->toThrow(InvalidArgumentException::class, 'Unknown backup scope');
});
