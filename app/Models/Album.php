<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'scope', 'title', 'slug', 'description',
    'cover_photo_id', 'sort_order', 'is_published',
])]
class Album extends Model
{
    use SoftDeletes;

    public const SCOPE_SITE = 'site';

    public const SCOPE_PLAYER = 'player';

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $album) {
            if (empty($album->slug) && ! empty($album->title)) {
                $album->slug = Str::slug($album->title, '-', 'ru');
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Photo, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Photo, $this>
     */
    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    public function isSite(): bool
    {
        return $this->scope === self::SCOPE_SITE;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSite(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_SITE);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
