<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Append an immutable entry to the admin audit log.
     *
     * @param  string  $action  Dot-separated code, e.g. 'password.changed'.
     * @param  Model|null  $subject  Target entity; defaults to null for pure events.
     * @param  array<string, mixed>|null  $old  Prior state snapshot (never secrets).
     * @param  array<string, mixed>|null  $new  New state snapshot (never secrets).
     * @param  User|null  $actor  Explicit actor; defaults to the authenticated user.
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?User $actor = null,
    ): AdminAuditLog {
        $actor ??= auth()->user();

        return AdminAuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => mb_substr($action, 0, 64),
            'subject_type' => $subject !== null ? mb_substr($subject::class, 0, 191) : null,
            'subject_id' => $subject?->getKey(),
            'old' => $old,
            'new' => $new,
            'ip' => $this->resolveIp(),
            'user_agent' => $this->resolveUserAgent(),
            'created_at' => now(),
        ]);
    }

    private function resolveIp(): ?string
    {
        $ip = request()->ip();

        return $ip !== null ? mb_substr($ip, 0, 45) : null;
    }

    private function resolveUserAgent(): ?string
    {
        $ua = request()->userAgent();

        return $ua !== null ? mb_substr($ua, 0, 255) : null;
    }
}
