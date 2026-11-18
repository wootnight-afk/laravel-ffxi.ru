<?php

namespace App\Models;

use App\Enums\GameJob;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single job level of a character (ADR-013 §2.8).
 *
 * One row per job per character, enforced by a unique index. Levels are
 * integers; "job not learned" is the absence of a row, not a zero level.
 *
 * @property int $game_character_id
 * @property GameJob $job
 * @property int $level
 */
#[Fillable(['game_character_id', 'job', 'level'])]
class GameCharacterJob extends Model
{
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'job' => GameJob::class,
        ];
    }

    /**
     * @return BelongsTo<GameCharacter, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'game_character_id');
    }
}
