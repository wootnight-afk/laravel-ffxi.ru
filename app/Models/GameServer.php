<?php

namespace App\Models;

use Database\Factories\GameServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Game server reference row (ADR-012 §2.1).
 *
 * Servers are never hard-deleted while characters reference them: the
 * foreign key is RESTRICT, and an obsolete server is deactivated instead.
 */
#[Fillable(['name', 'slug', 'description', 'sort_order', 'is_active'])]
class GameServer extends Model
{
    /** @use HasFactory<GameServerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GameCharacter, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(GameCharacter::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
