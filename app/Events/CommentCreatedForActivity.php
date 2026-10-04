<?php

namespace App\Events;

use App\Models\Comment;
use App\Models\User;

final readonly class CommentCreatedForActivity
{
    public function __construct(
        public Comment $comment,
        public User $actor,
    ) {}
}
