<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'action',
    'subject_type',
    'subject_id',
    'old',
    'new',
    'ip',
    'user_agent',
    'created_at',
])]
class AdminAuditLog extends Model
{
    /**
     * Immutable log: no updated_at column.
     */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old' => 'array',
            'new' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
