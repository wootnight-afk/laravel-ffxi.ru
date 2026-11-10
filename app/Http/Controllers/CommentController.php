<?php

namespace App\Http\Controllers;

use App\Events\CommentCreatedForActivity;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\News;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function store(StoreCommentRequest $request, News $news): JsonResponse
    {
        if (! $news->comments_enabled) {
            abort(403, __('site.comments.disabled'));
        }

        Gate::authorize('create', Comment::class);

        $data = $request->validated();

        $parentId = null;

        if (! empty($data['parent_id'])) {
            $parent = Comment::query()->find($data['parent_id']);

            // Разрешаем отвечать только на корневые комментарии (1 уровень вложенности).
            if ($parent === null || $parent->commentable_type !== News::class || $parent->commentable_id !== $news->id) {
                abort(422, __('site.comments.invalid_parent'));
            }

            $parentId = $parent->parent_id === null ? $parent->id : null;

            if ($parentId === null) {
                abort(422, __('site.comments.reply_root_only'));
            }
        }

        $status = $this->resolveModerationStatus($request->user()->id);

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => News::class,
            'commentable_id' => $news->id,
            'parent_id' => $parentId,
            'body' => $data['body'],
            'status' => $status,
        ]);

        if ($comment->status === Comment::STATUS_APPROVED) {
            DB::afterCommit(fn () => event(new CommentCreatedForActivity($comment, $request->user())));
        }

        return response()->json([
            'id' => $comment->id,
            'status' => $comment->status,
            'message' => $status === Comment::STATUS_APPROVED
                ? __('site.comments.published')
                : __('site.comments.submitted'),
        ], 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        Gate::authorize('update', $comment);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $comment->forceFill([
            'body' => $validated['body'],
            'edited_at' => now(),
        ])->save();

        return response()->json([
            'message' => __('site.comments.updated'),
        ]);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->json([
            'message' => __('site.comments.deleted'),
        ]);
    }

    public function report(Comment $comment): JsonResponse
    {
        Gate::authorize('report', $comment);

        if (! $comment->is_reported) {
            $comment->forceFill(['is_reported' => true])->save();
        }

        return response()->json([
            'message' => __('site.comments.reported_message'),
        ]);
    }

    private function resolveModerationStatus(int $userId): string
    {
        $mode = (string) $this->settings->string('comments_moderation', 'reputation');

        return match ($mode) {
            'none' => Comment::STATUS_APPROVED,
            'all' => Comment::STATUS_PENDING,
            'reputation' => $this->resolveReputationStatus($userId),
            default => Comment::STATUS_PENDING,
        };
    }

    private function resolveReputationStatus(int $userId): string
    {
        $approvedCount = Comment::query()
            ->where('user_id', $userId)
            ->where('status', Comment::STATUS_APPROVED)
            ->count();

        return $approvedCount >= 5
            ? Comment::STATUS_APPROVED
            : Comment::STATUS_PENDING;
    }
}
