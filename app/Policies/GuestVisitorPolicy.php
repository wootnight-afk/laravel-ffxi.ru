<?php

namespace App\Policies;

use App\Models\GuestVisitor;
use App\Models\User;

class GuestVisitorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') && $user->can('guests.view');
    }

    public function view(User $user, GuestVisitor $guestVisitor): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GuestVisitor $guestVisitor): bool
    {
        return false;
    }

    public function delete(User $user, GuestVisitor $guestVisitor): bool
    {
        return false;
    }
}
