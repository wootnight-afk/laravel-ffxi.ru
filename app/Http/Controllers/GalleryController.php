<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Comment;
use App\Models\Photo;
use Illuminate\Contracts\View\View;

class GalleryController extends Controller
{
    public function index(): View
    {
        $albums = Album::query()
            ->site()
            ->published()
            ->with(['photos' => fn ($q) => $q->where('is_published', true)->limit(6)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('gallery.index', [
            'albums' => $albums,
        ]);
    }

    public function showAlbum(Album $album): View
    {
        if (! $album->isSite() || ! $album->is_published) {
            abort(404);
        }

        $photos = $album->photos()
            ->where('is_published', true)
            ->with('user')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(24);

        return view('gallery.album', [
            'album' => $album,
            'photos' => $photos,
        ]);
    }

    public function showPhoto(Album $album, Photo $photo): View
    {
        if (! $album->isSite() || ! $album->is_published) {
            abort(404);
        }

        if ($photo->album_id !== $album->id || ! $photo->is_published) {
            abort(404);
        }

        $photo->load(['user', 'album']);

        $comments = $photo->comments()
            ->whereNull('parent_id')
            ->where('status', Comment::STATUS_APPROVED)
            ->with(['user', 'replies' => fn ($q) => $q->where('status', Comment::STATUS_APPROVED)->with('user')])
            ->orderBy('created_at')
            ->get();

        $prev = $album->photos()
            ->where('is_published', true)
            ->where('id', '<', $photo->id)
            ->orderByDesc('id')
            ->first();

        $next = $album->photos()
            ->where('is_published', true)
            ->where('id', '>', $photo->id)
            ->orderBy('id')
            ->first();

        return view('gallery.photo', [
            'album' => $album,
            'photo' => $photo,
            'comments' => $comments,
            'prevPhoto' => $prev,
            'nextPhoto' => $next,
        ]);
    }
}
