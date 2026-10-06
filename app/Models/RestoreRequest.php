<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending / applied restore request (ADR-004 R5, contract section 6).
 *
 * The row is the single-use capability that an admin creates in BackupPage
 * (HTTP) and that the CLI `app:rollback APPLY` consumes. HTTP never performs
 * the restore itself (ADR-004 R4).
 *
 * @property string $id
 * @property string $backup_id
 * @property int $admin_user_id
 * @property string $token
 * @property Carbon $expires_at
 * @property string $status
 * @property Carbon|null $consumed_at
 */
#[Fillable([
    'id',
    'backup_id',
    'admin_user_id',
    'token',
    'expires_at',
    'status',
    'consumed_at',
])]
class RestoreRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    /** @var array<int, string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPLIED,
        self::STATUS_FAILED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * A request may only be applied while it is pending and not expired
     * (contract section 6.7).
     */
    public function isUsable(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }
}
