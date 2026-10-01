<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Список slug'ов, которые не должны попадать в catch-all роут.
     */
    public const RESERVED_SLUGS = [
        'admin', 'guest', 'login', 'logout', 'register',
        'directory', 'news', 'gallery', 'events', 'cabinet',
        'activity', 'api', 'system', 'cookie', 'privacy',
        'players', 'password', 'email', 'up',
    ];

    public function show(string $slug): View
    {
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            abort(404);
        }

        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('pages.show', [
            'page' => $page,
        ]);
    }
}
