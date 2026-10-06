<?php

declare(strict_types=1);

require_once __DIR__.'/Stage9RestoreSupport.php';

use App\Models\AdminAuditLog;
use App\Models\RestoreRequest;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestoreRequestService;

beforeEach(function () {
    $this->env = bootStage9RestoreEnv();
    $this->admin = stage9Admin();
    $this->manifest = stage9MakeBackup();
    $this->request = app(RestoreRequestService::class)
        ->create($this->manifest->backupId, (int) $this->admin->getKey());
});

afterEach(function () {
    app()->maintenanceMode()->deactivate();
    stage9TearDown($this->env);
});

// ------------------------------------------------------------------
// LIST
// ------------------------------------------------------------------

it('lists the available backups', function () {
    $this->artisan('app:rollback', ['action' => 'LIST'])
        ->expectsOutputToContain($this->manifest->backupId)
        ->assertSuccessful();
});

it('reports when there are no backups', function () {
    app(BackupService::class)->delete($this->manifest->backupId);

    $this->artisan('app:rollback', ['action' => 'LIST'])
        ->expectsOutputToContain('No backups found.')
        ->assertSuccessful();
});

// ------------------------------------------------------------------
// CHECK
// ------------------------------------------------------------------

it('checks a valid request', function () {
    $this->artisan('app:rollback', ['action' => 'CHECK', 'id' => $this->request->id])
        ->assertSuccessful();
});

it('fails CHECK for an unknown request', function () {
    $this->artisan('app:rollback', ['action' => 'CHECK', 'id' => 'missing'])
        ->assertFailed();
});

it('fails CHECK without an id', function () {
    $this->artisan('app:rollback', ['action' => 'CHECK'])
        ->assertFailed();
});

it('rejects an expired request and audits the rejection', function () {
    $this->travel(31)->minutes();

    $this->artisan('app:rollback', ['action' => 'CHECK', 'id' => $this->request->id])
        ->assertFailed();

    expect($this->request->fresh()->status)->toBe(RestoreRequest::STATUS_PENDING)
        ->and(AdminAuditLog::query()->where('action', 'restore.rejected')->exists())->toBeTrue();
});

it('rejects a request with a tampered token', function () {
    $this->request->forceFill(['token' => str_repeat('0', 64)])->save();

    $this->artisan('app:rollback', ['action' => 'CHECK', 'id' => $this->request->id])
        ->assertFailed();
});

// ------------------------------------------------------------------
// APPLY
// ------------------------------------------------------------------

it('applies a valid request', function () {
    $this->artisan('app:rollback', ['action' => 'APPLY', 'id' => $this->request->id])
        ->assertSuccessful();

    expect($this->request->fresh()->status)->toBe(RestoreRequest::STATUS_APPLIED)
        ->and(AdminAuditLog::query()->where('action', 'restore.applied')->exists())->toBeTrue();
});

it('fails APPLY for an unknown request', function () {
    $this->artisan('app:rollback', ['action' => 'APPLY', 'id' => 'missing'])
        ->assertFailed();
});

it('fails APPLY when pre-checks fail and records the failure', function () {
    $this->travel(31)->minutes();

    $this->artisan('app:rollback', ['action' => 'APPLY', 'id' => $this->request->id])
        ->assertFailed();

    expect($this->request->fresh()->status)->toBe(RestoreRequest::STATUS_FAILED)
        ->and(AdminAuditLog::query()->where('action', 'restore.failed')->exists())->toBeTrue();
});

it('rejects an unknown action', function () {
    $this->artisan('app:rollback', ['action' => 'DROP'])
        ->assertFailed();
});
