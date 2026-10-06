<?php

namespace App\Policies;

use App\Models\AdminAuditLog;
use App\Models\User;

class AdminAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') && $user->can('audit.view');
    }

    public function view(User $user, AdminAuditLog $adminAuditLog): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AdminAuditLog $adminAuditLog): bool
    {
        return false;
    }

    public function delete(User $user, AdminAuditLog $adminAuditLog): bool
    {
        return false;
    }
}
