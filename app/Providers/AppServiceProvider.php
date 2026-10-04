<?php

namespace App\Providers;

use App\Models\GuestVisitor;
use App\Models\User;
use App\Policies\GuestVisitorPolicy;
use App\Policies\RolePolicy;
use App\Services\HtmlSanitizer;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, function () {
            return new ImageManager(new GdDriver);
        });

        $this->app->singleton(HtmlSanitizer::class);
        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(GuestVisitor::class, GuestVisitorPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        // Admin bypass for section.{key}.view abilities only.
        // Global bypass is intentionally NOT used: adminreview.md §11 forbids
        // an admin removing their own admin role or deleting themselves.
        // See ADR-007 and context.md §13.
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin() && str_starts_with($ability, 'section.')) {
                return true;
            }

            return null;
        });
    }
}
