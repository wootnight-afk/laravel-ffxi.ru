<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Models\Page;
use App\Services\ContactsPageContent;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Slug'и, зарезервированные под системные роуты.
     * Catch-all /{page:slug} не должен их перехватывать.
     */
    public const RESERVED_SLUGS = [
        'admin', 'guest', 'login', 'logout', 'register',
        'directory', 'news', 'gallery', 'events', 'cabinet',
        'activity', 'api', 'system', 'cookie', 'privacy',
        'players', 'password', 'email', 'up',
        'contacts', 'mfa', 'filament', 'storage',
    ];

    /**
     * Livewire registers its assets under a prefixed URI, so a CMS slug that
     * mimics that prefix would shadow the component endpoints.
     */
    public const RESERVED_SLUG_PREFIXES = ['livewire-'];

    public function contacts(ContactsPageContent $content): View
    {
        return view('contacts', $content->view());
    }

    public function show(Page $page): View
    {
        if (in_array($page->slug, self::RESERVED_SLUGS, true) || self::hasReservedPrefix($page->slug)) {
            abort(404);
        }

        if (! $page->is_published) {
            abort(404);
        }

        // Per-field fallback (ADR-011 §2.20): an empty English field uses the
        // Russian value; a page without any English translation stays in the
        // menu and is still displayed.
        $english = app()->getLocale() === Locale::En->value;

        $title = $english && filled($page->title_en) ? $page->title_en : $page->title;
        $bodyHtml = $english && filled($page->body_html_en) ? $page->body_html_en : $page->body_html;
        $metaTitle = $english && filled($page->meta_title_en) ? $page->meta_title_en : $page->meta_title;
        $metaDescription = $english && filled($page->meta_description_en)
            ? $page->meta_description_en
            : $page->meta_description;

        return view('pages.show', [
            'page' => $page,
            'title' => $title,
            'bodyHtml' => $bodyHtml,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
        ]);
    }

    /**
     * Reserved prefix check. Public and static because the slug of a CMS page
     * is guarded in three places — this route, the editor validation and the
     * automatic generation — and all of them must agree.
     */
    public static function hasReservedPrefix(string $slug): bool
    {
        foreach (self::RESERVED_SLUG_PREFIXES as $prefix) {
            if (str_starts_with($slug, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
