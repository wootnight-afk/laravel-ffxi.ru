<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Services\AuditLogger;

it('records the request IP for security events by default', function () {
    $log = app(AuditLogger::class)->log('restore.requested');

    expect($log->ip)->not->toBeNull();
});

it('omits the IP for non-security events when recordIp is false', function () {
    $log = app(AuditLogger::class)->log('backup.created', recordIp: false);

    expect($log->ip)->toBeNull()
        ->and($log->user_agent)->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'backup.created')->exists())->toBeTrue();
});
