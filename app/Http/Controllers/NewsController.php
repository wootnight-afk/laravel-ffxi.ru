<?php

namespace App\Http\Controllers;

use App\Models\Comment;
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
            ->with(['user'])
            ->firstOrFail();

        if (! $news->isPublished()) {
            $user = request()->user();
            if (! $user || ! $user->can('news.manage_site')) {
                abort(404);
            }
        }

        $news->increment('views');

        $user = request()->user();

        $comments = $news->comments()
            ->whereNull('parent_id')
            ->where(function ($q) use ($user) {
                $q->where('status', Comment::STATUS_APPROVED);

                if ($user !== null) {
                    $q->orWhere(function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)
                            ->whereIn('status', [
                                Comment::STATUS_PENDING,
                                Comment::STATUS_REJECTED,
                            ]);
                    });
                }
            })
            ->with([
                'user',
                'replies' => fn ($q) => $q->where('status', Comment::STATUS_APPROVED)
                    ->with('user')
                    ->orderBy('created_at'),
            ])
            ->orderBy('created_at')
            ->get();

        return view('news.show', [
            'news' => $news,
            'comments' => $comments,
        ]);
    }
}
