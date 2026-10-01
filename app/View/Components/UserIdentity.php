<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UserIdentity extends Component
{
    public function __construct(
        public readonly User $user,
        public readonly string $context = 'default',
    ) {}

    public function render(): View
    {
        $viewer = auth()->user();

        $isOwner = $viewer !== null && $viewer->id === $this->user->id;
        $isAdmin = $viewer !== null && $viewer->isAdmin();
        $isPrivileged = $isOwner || $isAdmin;

        $isProfileOpen = $this->user->isProfilePublic();
        $isChatContext = $this->context === 'chat';

        $showLink = $viewer !== null && ($isPrivileged || $isProfileOpen);
        $showAvatar = $viewer !== null && ($isPrivileged || ! $isChatContext);
        $showRank = $showAvatar && $this->user->rank_id !== null;

        return view('components.user-identity', [
            'user' => $this->user,
            'showLink' => $showLink,
            'showAvatar' => $showAvatar,
            'showRank' => $showRank,
        ]);
    }
}
