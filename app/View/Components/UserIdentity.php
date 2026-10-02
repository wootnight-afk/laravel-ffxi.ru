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
        // Deleted accounts are shown as a neutral label, without link,
        // avatar or rank — for every viewer (frontend-spec §6.12).
        if ($this->user->isDeletionRequested()) {
            return view('components.user-identity', [
                'user' => $this->user,
                'deleted' => true,
                'showLink' => false,
                'showAvatar' => false,
                'showRank' => false,
            ]);
        }

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
            'deleted' => false,
            'showLink' => $showLink,
            'showAvatar' => $showAvatar,
            'showRank' => $showRank,
        ]);
    }
}
