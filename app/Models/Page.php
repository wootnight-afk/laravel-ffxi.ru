<?php

namespace App\Models;

use App\Exceptions\SlugGenerationException;
use App\Http\Controllers\PageController;
use App\Services\ContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'title_en', 'slug', 'body', 'body_html', 'body_en', 'body_html_en',
    'is_published', 'show_in_menu', 'show_in_footer', 'menu_order',
    'meta_title', 'meta_title_en', 'meta_description', 'meta_description_en',
])]
class Page extends Model
{
    use SoftDeletes;

    /** Slug column width (see the `pages.slug` migration). */
    public const SLUG_MAX_LENGTH = 120;

    /** `base`, `base-2` … `base-N` — then the caller has to pick a slug itself. */
    public const AUTO_SLUG_MAX_ATTEMPTS = 50;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_in_menu' => 'boolean',
            'show_in_footer' => 'boolean',
            'menu_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $page) {
            if (empty($page->slug) && ! empty($page->title)) {
                $page->slug = static::generateUniqueSlug((string) $page->title, $page);
            }

            /** @var ContentRenderer $renderer */
            $renderer = app(ContentRenderer::class);

            if ($page->isDirty('body')) {
                $page->body_html = $renderer->render($page->body);
            }

            if ($page->isDirty('body_en')) {
                $page->body_html_en = filled($page->body_en)
                    ? $renderer->render($page->body_en)
                    : null;
            }
        });
    }

    /**
     * Slug derived from the title when none was given. The candidate skips
     * reserved routes and the slugs of other active pages — writing either of
     * them would break the whole save on the `pages_slug_key_unique` index.
     *
     * @throws SlugGenerationException when every candidate is taken.
     */
    public static function generateUniqueSlug(string $title, ?self $excluding = null): string
    {
        // Room for the `-N` suffix inside the column width.
        $base = rtrim(Str::limit(Str::slug($title, '-', 'ru'), self::SLUG_MAX_LENGTH - 4, ''), '-');

        if ($base === '') {
            $base = 'page';
        }

        // A suffix would keep a reserved prefix (`livewire-x-2` is still
        // reserved), so such a base gets a neutral one instead.
        if (static::isReservedSlug($base)) {
            $base = 'page-'.$base;
        }

        $candidate = $base;

        for ($attempt = 1; $attempt <= self::AUTO_SLUG_MAX_ATTEMPTS; $attempt++) {
            if (static::slugIsAvailable($candidate, $excluding)) {
                return $candidate;
            }

            $candidate = $base.'-'.($attempt + 1);
        }

        throw SlugGenerationException::forTitle($title);
    }

    /**
     * Reserved route or reserved prefix. Both lists live with the public route
     * (PageController), so the editor validation, the generator and the router
     * can never drift apart.
     */
    public static function isReservedSlug(string $slug): bool
    {
        return in_array($slug, PageController::RESERVED_SLUGS, true)
            || PageController::hasReservedPrefix($slug);
    }

    /**
     * Availability follows the `slug_key` rule: a soft-deleted page does not
     * hold its slug, so the default (non-trashed) scope is the right check.
     */
    public static function slugIsAvailable(string $slug, ?self $excluding = null): bool
    {
        if (static::isReservedSlug($slug)) {
            return false;
        }

        return ! static::query()
            ->where('slug', $slug)
            ->when(
                $excluding !== null && $excluding->exists,
                fn (Builder $query) => $query->whereKeyNot($excluding->getKey())
            )
            ->exists();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInMenu(Builder $query): Builder
    {
        return $query->where('show_in_menu', true)
            ->orderBy('menu_order')
            ->orderBy('title');
    }
}
