<?php

declare(strict_types=1);

require_once __DIR__.'/Stage9RestoreSupport.php';

use App\Models\AdminAuditLog;
use App\Models\RestoreRequest;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestoreRequestService;
use App\Services\Backup\RestoreService;

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

function stage9RestoreService(): RestoreService
{
    return app(RestoreService::class);
}

// ------------------------------------------------------------------
// Read-only checks
// ------------------------------------------------------------------

it('passes the read-only checks for a valid request', function () {
    expect(stage9RestoreService()->check($this->request))->toBe([]);
});

it('flags an expired request', function () {
    $this->travel(31)->minutes();

    $problems = stage9RestoreService()->check($this->request->fresh());

    expect($problems)->not->toBe([])
        ->and(implode(' ', $problems))->toContain('expired');
});

it('flags a tampered token', function () {
    $this->request->forceFill(['token' => str_repeat('0', 64)])->save();

    $problems = stage9RestoreService()->check($this->request->fresh());

    expect(implode(' ', $problems))->toContain('HMAC');
});

it('flags a missing backup', function () {
    $request = app(RestoreRequestService::class)
        ->create('00000000-0000-0000-0000-000000000000', (int) $this->admin->getKey());

    $problems = stage9RestoreService()->check($request);

    expect(implode(' ', $problems))->toContain('was not found');
});

// ------------------------------------------------------------------
// Apply
// ------------------------------------------------------------------

it('applies a valid request: restores db and files, audits, and leaves maintenance off', function () {
    stage9RestoreService()->apply($this->request);

    $request = $this->request->fresh();

    expect($request->status)->toBe(RestoreRequest::STATUS_APPLIED)
        ->and($request->consumed_at)->not->toBeNull()
        ->and(app()->maintenanceMode()->active())->toBeFalse();

    // The decompressed dump reached the mysql client.
    expect(file_get_contents($this->env['mysql_out']))->toBe("-- fake dump\n");

    // tar was invoked to extract the files archive into the project root.
    $tarArgs = file_get_contents($this->env['tar_out']);
    expect($tarArgs)->toContain('-xzf')->toContain('-C')->toContain(base_path());

    // The auto-backup of the current state was created (original + auto).
    expect(app(BackupService::class)->list())->toHaveCount(2);

    expect(AdminAuditLog::query()->where('action', 'restore.applied')->exists())->toBeTrue();
});

it('refuses to apply when the request is expired and audits the failure', function () {
    $this->travel(31)->minutes();

    expect(fn () => stage9RestoreService()->apply($this->request->fresh()))
        ->toThrow(RuntimeException::class);

    expect($this->request->fresh()->status)->toBe(RestoreRequest::STATUS_FAILED)
        ->and(AdminAuditLog::query()->where('action', 'restore.failed')->exists())->toBeTrue()
        ->and(app()->maintenanceMode()->active())->toBeFalse();

    // No auto-backup was created: only the original backup exists.
    expect(app(BackupService::class)->list())->toHaveCount(1);
});

it('refuses to apply when the backup lock is already held', function () {
    $holder = new BackupLock;
    expect($holder->acquire('backup'))->toBeTrue();

    try {
        expect(fn () => stage9RestoreService()->apply($this->request))
            ->toThrow(RuntimeException::class, 'already running');

        expect($this->request->fresh()->status)->toBe(RestoreRequest::STATUS_FAILED);
    } finally {
        $holder->release('backup');
    }
});

it('keeps maintenance mode on when the restore fails after it started', function () {
    config(['backup.binaries.mysql' => '/nonexistent/ffxi-mysql']);

    expect(fn () => stage9RestoreService()->apply($this->request))
        ->toThrow(RuntimeException::class);

    expect(app()->maintenanceMode()->active())->toBeTrue()
        ->and($this->request->fresh()->status)->toBe(RestoreRequest::STATUS_FAILED)
        ->and(AdminAuditLog::query()->where('action', 'restore.failed')->exists())->toBeTrue();
});
