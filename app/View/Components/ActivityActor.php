<?php

namespace App\View\Components;

use App\Models\User;
use App\Services\ActivitySubjectResolver;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ActivityActor extends Component
{
    public function __construct(
        public readonly ?User $actor,
        public readonly ?User $viewer,
    ) {}

    public function render(): View
    {
        if ($this->actor === null) {
            return view('activity._actor-deleted');
        }

        return view('activity._actor', [
            'actor' => $this->actor,
            'actorLinkable' => app(ActivitySubjectResolver::class)->actorLinkable($this->actor, $this->viewer),
        ]);
    }
}
