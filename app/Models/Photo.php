<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'album_id', 'user_id',
    'path_original', 'path_medium', 'path_thumb', 'path_preview',
    'caption', 'caption_en', 'taken_at', 'exif',
    'title', 'title_en', 'summary', 'summary_en', 'details', 'details_en',
    'show_title_in_lightbox', 'show_summary_in_lightbox',
    'width', 'height', 'size_bytes',
    'sort_order', 'is_published',
])]
class Photo extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'exif' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'show_title_in_lightbox' => 'boolean',
            'show_summary_in_lightbox' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function urlMedium(): string
    {
        return $this->path_medium
            ? Storage::disk('public')->url($this->path_medium)
            : Storage::disk('public')->url($this->path_original);
    }

    public function urlThumb(): string
    {
        if ($this->path_preview) {
            return Storage::disk('public')->url($this->path_preview);
        }

        return $this->path_thumb
            ? Storage::disk('public')->url($this->path_thumb)
            : $this->urlMedium();
    }

    public function urlOriginal(): string
    {
        return Storage::disk('public')->url($this->path_original);
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
