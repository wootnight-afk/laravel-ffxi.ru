<?php

namespace App\Models;

use App\Enums\CharacterRace;
use App\Enums\UserStatus;
use Database\Factories\GameCharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Game character of a site account (ADR-012 §2.2).
 *
 * Visibility is a per-character switch, independent of the account privacy
 * flags: a hidden character stays fully functional inside the site but never
 * appears in public listings. The account owner always sees their own
 * characters regardless of the switch.
 */
#[Fillable([
    'user_id', 'game_server_id', 'name', 'is_public',
    'race', 'main_job', 'legend', 'legend_html',
    'legend_ru', 'legend_ru_html', 'legend_en', 'legend_en_html',
])]
class GameCharacter extends Model
{
    /** @use HasFactory<GameCharacterFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'race' => CharacterRace::class,
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
     * @return BelongsTo<GameServer, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(GameServer::class, 'game_server_id');
    }

    /**
     * Job levels of the character (ADR-013 §2.8).
     *
     * @return HasMany<GameCharacterJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(GameCharacterJob::class);
    }

    /**
     * Level of the main job, if the job is listed among the character jobs.
     */
    public function mainJobLevel(): ?int
    {
        if ($this->main_job === null) {
            return null;
        }

        return $this->jobs
            ->firstWhere('job.value', $this->main_job)
            ?->level;
    }

    /**
     * Original Markdown of the legend in the requested language (STAGE-1 §16).
     *
     * A locale outside RU/EN has no stored legend and returns null: the text of
     * another language is never substituted.
     */
    public function legendText(?string $locale): ?string
    {
        $column = $this->legendColumn($locale);

        return $column === null ? null : $this->{$column};
    }

    /**
     * Sanitized HTML of the legend in the requested language.
     */
    public function legendHtml(?string $locale): ?string
    {
        $column = $this->legendColumn($locale);

        return $column === null ? null : $this->{$column.'_html'};
    }

    private function legendColumn(?string $locale): ?string
    {
        return match ($locale) {
            'ru' => 'legend_ru',
            'en' => 'legend_en',
            default => null,
        };
    }

    /**
     * Characters that may appear in public listings: the switch is on and the
     * owning account is neither deleted nor suspended.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)
            ->whereHas('user', fn (Builder $q) => $q->where('status', UserStatus::Active));
    }
}
