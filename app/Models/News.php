<?php

namespace App\Models;

use App\Services\ContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'scope', 'title', 'slug', 'excerpt',
    'body', 'body_html', 'cover_path',
    'is_pinned', 'comments_enabled', 'status',
    'rejection_reason', 'published_at', 'views',
])]
class News extends Model
{
    use SoftDeletes;

    public const SCOPE_SITE = 'site';
    public const SCOPE_PLAYER = 'player';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'comments_enabled' => 'boolean',
            'published_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $news) {
            if (empty($news->slug) && ! empty($news->title)) {
                $news->slug = Str::slug($news->title);
            }

            if ($news->isDirty('body')) {
                /** @var ContentRenderer $renderer */
                $renderer = app(ContentRenderer::class);
                $news->body_html = $renderer->render($news->body);

                if (empty($news->excerpt)) {
                    $news->excerpt = Str::limit(strip_tags((string) $news->body), 300);
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function isSite(): bool
    {
        return $this->scope === self::SCOPE_SITE;
    }

    public function isPlayer(): bool
    {
        return $this->scope === self::SCOPE_PLAYER;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeSite(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_SITE);
    }

    public function scopePlayer(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_PLAYER);
    }

    public function scopePinnedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('published_at');
    }
}
