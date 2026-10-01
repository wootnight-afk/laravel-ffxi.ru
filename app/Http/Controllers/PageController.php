<?php

namespace App\Http\Controllers;

use App\Models\Page;
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
    ];

    public function show(Page $page): View
    {
        if (in_array($page->slug, self::RESERVED_SLUGS, true)) {
            abort(404);
        }

        if (! $page->is_published) {
            abort(404);
        }

        return view('pages.show', [
            'page' => $page,
        ]);
    }
}
