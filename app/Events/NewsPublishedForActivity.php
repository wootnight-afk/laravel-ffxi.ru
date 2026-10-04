<?php

namespace App\Events;

use App\Models\News;
use App\Models\User;

final readonly class NewsPublishedForActivity
{
    public function __construct(
        public News $news,
        public User $actor,
    ) {}
}
