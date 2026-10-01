<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Component;

class Avatar extends Component
{
    public function __construct(
        public readonly User $user,
        public readonly int $size = 48,
    ) {}

    public function render(): View
    {
        $url = null;

        if ($this->user->avatar_path) {
            $url = Storage::disk('public')->url($this->user->avatar_path);
        }

        $initial = mb_strtoupper(mb_substr($this->user->name, 0, 1));
        $hue = abs(crc32($this->user->name)) % 360;
        $backgroundColor = "hsl({$hue}, 45%, 45%)";

        return view('components.avatar', [
            'url' => $url,
            'initial' => $initial,
            'size' => $this->size,
            'backgroundColor' => $backgroundColor,
        ]);
    }
}
