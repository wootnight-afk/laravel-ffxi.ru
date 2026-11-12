<?php

namespace App\Services;

use App\Models\Comment;

/**
 * Single source of truth for the moderation status of a new comment
 * (ADR/news + events). Extracted from CommentController and
 * EventCommentController, whose implementations were identical.
 */
class CommentModerationResolver
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function resolve(int $userId): string
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
