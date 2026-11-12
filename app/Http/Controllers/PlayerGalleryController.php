<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Models\Album;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Public view of a player's personal gallery (scope = player).
 *
 * Access is owned by AlbumPolicy::view(): the owner and admins always see the
 * gallery, everyone else only when the profile is public AND the gallery
 * switch is on. Unpublished photos stay invisible to strangers.
 */
class PlayerGalleryController extends Controller
{
    public function index(Request $request, User $user): View
    {
        $this->assertProfileAvailable($user);

        $viewer = $request->user();
        $canSeeUnpublished = $this->canSeeUnpublished($viewer, $user);

        $albums = Album::query()
            ->where('user_id', $user->id)
            ->where('scope', Album::SCOPE_PLAYER)
            ->when(! $canSeeUnpublished, fn ($q) => $q->where('is_published', true))
            ->with(['photos' => fn ($q) => $q->where('is_published', true)->limit(6)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('gallery.player-index', [
            'user' => $user,
            'albums' => $albums,
        ]);
    }

    public function showAlbum(Request $request, User $user, Album $album): View
    {
        $this->assertProfileAvailable($user);
        $this->assertAlbumBelongsTo($user, $album);

        Gate::authorize('view', $album);

        $viewer = $request->user();
        $canSeeUnpublished = $this->canSeeUnpublished($viewer, $user);

        $photos = $album->photos()
            ->when(! $canSeeUnpublished, fn ($q) => $q->where('is_published', true))
            ->with('user')
            ->paginate(24);

        return view('gallery.album', [
            'album' => $album,
            'photos' => $photos,
            'title' => $this->localized($album->title, $album->title_en),
            'description' => $this->localized($album->description, $album->description_en),
            'routePrefix' => 'gallery.player',
            'routeParams' => ['user' => $user->name],
        ]);
    }

    public function showPhoto(Request $request, User $user, Album $album, Photo $photo): View
    {
        $this->assertProfileAvailable($user);
        $this->assertAlbumBelongsTo($user, $album);
        $this->assertPhotoBelongsTo($album, $photo);

        Gate::authorize('view', $album);

        $viewer = $request->user();
        $canSeeUnpublished = $this->canSeeUnpublished($viewer, $user);

        if (! $photo->is_published && ! $canSeeUnpublished) {
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
            ->when(! $canSeeUnpublished, fn ($q) => $q->where('is_published', true))
            ->where('id', '<', $photo->id)
            ->orderByDesc('id')
            ->first();

        $next = $album->photos()
            ->when(! $canSeeUnpublished, fn ($q) => $q->where('is_published', true))
            ->where('id', '>', $photo->id)
            ->orderBy('id')
            ->first();

        return view('gallery.photo', [
            'album' => $album,
            'photo' => $photo,
            'comments' => $comments,
            'prevPhoto' => $prev,
            'nextPhoto' => $next,
            'title' => $this->localized($album->title, $album->title_en),
            'caption' => $this->localized($photo->caption, $photo->caption_en),
            'details' => $this->localized($photo->details, $photo->details_en),
            'routePrefix' => 'gallery.player',
            'routeParams' => ['user' => $user->name],
        ]);
    }

    private function assertProfileAvailable(User $user): void
    {
        if ($user->isDeletionRequested() || $user->isSuspended()) {
            abort(404);
        }
    }

    private function assertAlbumBelongsTo(User $user, Album $album): void
    {
        abort_unless($album->user_id === $user->id && ! $album->isSite(), 404);
    }

    private function assertPhotoBelongsTo(Album $album, Photo $photo): void
    {
        abort_unless($photo->album_id === $album->id, 404);
    }

    private function canSeeUnpublished(?User $viewer, User $owner): bool
    {
        return $viewer !== null && ($viewer->id === $owner->id || $viewer->isAdmin());
    }

    private function localized(?string $russian, ?string $english): ?string
    {
        return app()->getLocale() === Locale::En->value && filled($english)
            ? $english
            : $russian;
    }
}
