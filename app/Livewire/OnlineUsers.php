<?php

namespace App\Livewire;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OnlineUsers extends Component
{
    public function mount(): void
    {
        $this->authenticatedUser();
    }

    public function refreshOnlineUsers(): void
    {
        $this->authenticatedUser();
    }

    public function render(): View
    {
        $this->authenticatedUser();
        $cutoff = now()->subMinutes(5);
        $onlineQuery = User::query()
            ->where('status', UserStatus::Active)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $cutoff);

        return view('livewire.online-users', [
            'onlineCount' => (clone $onlineQuery)->count(),
            'onlineUsers' => (clone $onlineQuery)
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'viewer' => Auth::user(),
        ]);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
