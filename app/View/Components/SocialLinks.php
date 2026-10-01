<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SocialLinks extends Component
{
    public function __construct(
        public readonly User $user,
    ) {}

    public function render(): View
    {
        if (auth()->guest() || ! $this->user->isProfilePublic()) {
            return view('components.social-links', [
                'show' => false,
                'links' => collect(),
            ]);
        }

        $links = $this->user->socialLinks()
            ->where('is_visible', true)
            ->get();

        return view('components.social-links', [
            'show' => $links->isNotEmpty(),
            'links' => $links,
        ]);
    }
}
