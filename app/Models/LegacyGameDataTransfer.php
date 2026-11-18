<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record of the legacy game data transfer (ADR-013 §2.10).
 *
 * Both sides of the transfer are stored: the account value that acted as the
 * source and the character value that was the target, plus the control hash of
 * what was actually written. The rollback uses the control hash, never the
 * normalised source hash, to decide whether a field may be restored.
 */
#[Fillable([
    'batch_id', 'user_id', 'character_id', 'field', 'field_group', 'status',
    'source_value', 'source_hash', 'target_value_before', 'target_hash_before',
    'applied_hash', 'applied_at', 'reverted_at', 'reverted_by', 'note',
])]
class LegacyGameDataTransfer extends Model
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_SKIPPED_CONFLICT = 'skipped_conflict';

    public const STATUS_SKIPPED_NO_TARGET = 'skipped_no_target';

    public const STATUS_SKIPPED_NO_SOURCE = 'skipped_no_source';

    public const STATUS_SKIPPED_MODIFIED = 'skipped_modified';

    public const STATUS_REVERTED = 'reverted';

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<GameCharacter, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id')->withTrashed();
    }
}
