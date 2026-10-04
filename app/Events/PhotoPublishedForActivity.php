<?php

namespace App\Events;

use App\Models\Photo;
use App\Models\User;

final readonly class PhotoPublishedForActivity
{
    public function __construct(
        public Photo $photo,
        public User $actor,
    ) {}
}
