<?php

namespace App\View\Components;

use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UserRank extends Component
{
    public function __construct(
        public readonly User $user,
        public readonly bool $compact = true,
    ) {}

    public function render(): View
    {
        $ranksEnabled = app(SettingsRepository::class)->bool('ranks_enabled', true);

        $show = auth()->check()
            && $ranksEnabled
            && $this->user->rank !== null;

        return view('components.user-rank', [
            'show' => $show,
            'rank' => $this->user->rank,
            'compact' => $this->compact,
        ]);
    }
}
