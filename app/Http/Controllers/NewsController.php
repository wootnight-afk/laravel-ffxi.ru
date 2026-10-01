<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $news = News::query()
            ->site()
            ->published()
            ->with('user')
            ->pinnedFirst()
            ->paginate(10);

        return view('news.index', [
            'news' => $news,
        ]);
    }

    public function show(string $slug): View
    {
        $news = News::query()
            ->site()
            ->where('slug', $slug)
            ->with(['user', 'comments' => fn ($q) => $q->visible()->with('user')->latest()])
            ->firstOrFail();

        if (! $news->isPublished()) {
            $user = request()->user();
            if (! $user || ! $user->can('news.manage_site')) {
                abort(404);
            }
        }

        // Инкремент просмотров — простой, без атомарности. Допустимо для блога.
        $news->increment('views');

        return view('news.show', [
            'news' => $news,
        ]);
    }
}
