<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Event;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EventCommentController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function store(StoreCommentRequest $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $event);
        Gate::authorize('create', Comment::class);

        $data = $request->validated();
        $parentId = null;

        if (! empty($data['parent_id'])) {
            $parent = Comment::query()->find($data['parent_id']);

            if (
                $parent === null
                || $parent->commentable_type !== Event::class
                || $parent->commentable_id !== $event->id
            ) {
                abort(422, 'Недопустимый родительский комментарий.');
            }

            $parentId = $parent->parent_id === null ? $parent->id : null;

            if ($parentId === null) {
                abort(422, 'Ответ можно оставить только на корневой комментарий.');
            }
        }

        $status = $this->resolveModerationStatus($request->user()->id);
        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => Event::class,
            'commentable_id' => $event->id,
            'parent_id' => $parentId,
            'body' => $data['body'],
            'status' => $status,
        ]);

        $message = $status === Comment::STATUS_APPROVED
            ? 'Комментарий опубликован.'
            : 'Комментарий отправлен на модерацию.';

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $comment->id,
                'status' => $comment->status,
                'message' => $message,
            ], 201);
        }

        return redirect()
            ->route('events.show', $event)
            ->with('status', $message);
    }

    private function resolveModerationStatus(int $userId): string
    {
        $mode = $this->settings->string('comments_moderation', 'reputation');

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
