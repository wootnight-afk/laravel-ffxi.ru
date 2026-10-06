<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\RestoreRequest;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Creates and validates restore requests (ADR-004 R5, contract section 3.7).
 *
 * A request carries an HMAC-SHA256 token over
 * `(request_id|backup_id|admin_user_id|expires_at)`, keyed by APP_KEY. The
 * token is never shown to the admin; it only proves the stored row was not
 * tampered with before the CLI consumes it. The expiry is 30 minutes.
 */
class RestoreRequestService
{
    public const TTL_MINUTES = 30;

    /**
     * Create a pending restore request for a backup.
     */
    public function create(string $backupId, int $adminUserId): RestoreRequest
    {
        $id = (string) Str::uuid();
        $expiresAt = Carbon::now()->utc()->addMinutes(self::TTL_MINUTES);

        return RestoreRequest::create([
            'id' => $id,
            'backup_id' => $backupId,
            'admin_user_id' => $adminUserId,
            'token' => $this->sign($id, $backupId, $adminUserId, $expiresAt),
            'expires_at' => $expiresAt,
            'status' => RestoreRequest::STATUS_PENDING,
            'consumed_at' => null,
        ]);
    }

    public function find(string $id): ?RestoreRequest
    {
        return RestoreRequest::query()->find($id);
    }

    /**
     * Recompute the token from the stored fields and compare it in constant
     * time. A mismatch means the row was modified outside the service.
     */
    public function tokenMatches(RestoreRequest $request): bool
    {
        $expected = $this->sign(
            $request->id,
            $request->backup_id,
            (int) $request->admin_user_id,
            $request->expires_at,
        );

        return hash_equals($expected, $request->token);
    }

    /**
     * HMAC-SHA256 of the request identity, keyed by APP_KEY (ADR-004 R5).
     *
     * `expires_at` is signed as a Unix timestamp so the payload is independent
     * of the database timezone and column precision.
     */
    private function sign(string $id, string $backupId, int $adminUserId, DateTimeInterface $expiresAt): string
    {
        $payload = implode('|', [
            $id,
            $backupId,
            (string) $adminUserId,
            (string) Carbon::instance($expiresAt)->getTimestamp(),
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
