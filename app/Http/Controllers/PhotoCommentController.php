<?php

namespace App\Http\Controllers;

use App\Events\CommentCreatedForActivity;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Album;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\User;
use App\Services\CommentModerationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Comments for gallery photos (both site albums and personal player albums).
 *
 * Reuses Comment, CommentPolicy and Photo::comments(); the commentable object
 * is always the Photo. Mirrors the protections of CommentController and
 * EventCommentController and adds ownership checks, because a photo URL can be
 * forged: the photo must belong to the album in the URL and the album must
 * belong to the user in the URL.
 */
class PhotoCommentController extends Controller
{
    public function __construct(
        private readonly CommentModerationResolver $moderation,
    ) {}

    public function storeSite(StoreCommentRequest $request, Album $album, Photo $photo): JsonResponse|RedirectResponse
    {
        abort_unless($album->isSite(), 404);

        return $this->handle($request, $album, $photo, null);
    }

    public function storePlayer(StoreCommentRequest $request, User $user, Album $album, Photo $photo): JsonResponse|RedirectResponse
    {
        abort_unless($album->user_id === $user->id && ! $album->isSite(), 404);

        return $this->handle($request, $album, $photo, $user);
    }

    private function handle(
        StoreCommentRequest $request,
        Album $album,
        Photo $photo,
        ?User $owner,
    ): JsonResponse|RedirectResponse {
        // Ownership first: a policy alone cannot tell that the photo belongs
        // to the album or the album to the user from the URL.
        abort_unless($photo->album_id === $album->id, 404);

        Gate::authorize('view', $album);
        Gate::authorize('view', $photo);
        Gate::authorize('create', Comment::class);

        $data = $request->validated();
        $parentId = null;

        if (! empty($data['parent_id'])) {
            $parent = Comment::query()->find($data['parent_id']);

            // Replies are allowed to root comments of this very photo only.
            if (
                $parent === null
                || $parent->commentable_type !== Photo::class
                || $parent->commentable_id !== $photo->id
            ) {
                abort(422, __('site.comments.invalid_parent'));
            }

            $parentId = $parent->parent_id === null ? $parent->id : null;

            if ($parentId === null) {
                abort(422, __('site.comments.reply_root_only'));
            }
        }

        $status = $this->moderation->resolve($request->user()->id);

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => Photo::class,
            'commentable_id' => $photo->id,
            'parent_id' => $parentId,
            'body' => $data['body'],
            'status' => $status,
        ]);

        if ($comment->status === Comment::STATUS_APPROVED) {
            DB::afterCommit(fn () => event(new CommentCreatedForActivity($comment, $request->user())));
        }

        $message = $status === Comment::STATUS_APPROVED
            ? __('site.comments.published')
            : __('site.comments.submitted');

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $comment->id,
                'status' => $comment->status,
                'message' => $message,
            ], 201);
        }

        $redirect = $owner === null
            ? route('gallery.photo', [$album->slug, $photo->id])
            : route('gallery.player.photo', [$owner->name, $album->slug, $photo->id]);

        return redirect()->to($redirect)->with('status', $message);
    }
}
