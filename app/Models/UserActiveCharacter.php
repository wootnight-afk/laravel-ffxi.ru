<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Active character of an account (ADR-013 §2.2, STAGE-2 §3).
 *
 * One row per account: `user_id` is the primary key. The row exists only while
 * the account has an explicit choice; the absence of the row means "no active
 * character" and is a normal state, not an error.
 *
 * The schema — `UNIQUE (character_id)` plus the composite foreign key
 * (character_id, user_id) → game_characters (id, user_id) — makes a pointer to
 * a character of another account impossible. Writes go through
 * CharacterContext only, so no controller or view touches this model.
 */
#[Fillable(['user_id', 'character_id'])]
class UserActiveCharacter extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

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
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }
}
