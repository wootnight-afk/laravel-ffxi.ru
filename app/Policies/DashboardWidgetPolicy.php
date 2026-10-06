<?php

namespace App\Policies;

use App\Models\DashboardWidget;
use App\Models\User;

class DashboardWidgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('widgets.manage');
    }

    public function view(User $user, DashboardWidget $dashboardWidget): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DashboardWidget $dashboardWidget): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, DashboardWidget $dashboardWidget): bool
    {
        return $this->viewAny($user);
    }
}
