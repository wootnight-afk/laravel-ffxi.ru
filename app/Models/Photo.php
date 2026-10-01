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
    'path_original', 'path_medium', 'path_thumb',
    'caption', 'taken_at', 'exif',
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
        ];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
        return $this->path_thumb
            ? Storage::disk('public')->url($this->path_thumb)
            : $this->urlMedium();
    }

    public function urlOriginal(): string
    {
        return Storage::disk('public')->url($this->path_original);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
