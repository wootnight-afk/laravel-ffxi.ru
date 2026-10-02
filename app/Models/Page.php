<?php

namespace App\Models;

use App\Services\ContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'slug', 'body', 'body_html',
    'is_published', 'show_in_menu', 'menu_order',
    'meta_title', 'meta_description',
])]
class Page extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_in_menu' => 'boolean',
            'menu_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $page) {
            if (empty($page->slug) && ! empty($page->title)) {
                $page->slug = Str::slug($page->title, '-', 'ru');
            }

            if ($page->isDirty('body')) {
                /** @var ContentRenderer $renderer */
                $renderer = app(ContentRenderer::class);
                $page->body_html = $renderer->render($page->body);
            }
        });
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
