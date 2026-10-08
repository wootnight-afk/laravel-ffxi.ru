<?php

namespace App\Providers;

use App\Contracts\BackupStorage;
use App\Http\Middleware\RequireMfa;
use App\Models\GuestVisitor;
use App\Models\User;
use App\Policies\GuestVisitorPolicy;
use App\Policies\RolePolicy;
use App\Services\Backup\LocalBackupStorage;
use App\Services\HtmlSanitizer;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Livewire\Livewire;
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

        // Backup subsystem (Stage 9). The storage abstraction is bound to the
        // local disk implementation so BackupService can be resolved from the
        // container (CLI, scheduler, Filament page). An external store can be
        // swapped in here later without touching the service.
        $this->app->bind(BackupStorage::class, LocalBackupStorage::class);
    }

    public function boot(): void
    {
        Gate::policy(GuestVisitor::class, GuestVisitorPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        // Unified MFA enforcement must also cover Livewire interactions, which
        // bypass route middleware. Persistent middleware is re-applied to the
        // Livewire update request using the original page route (ADR-009 §2.3).
        Livewire::addPersistentMiddleware([RequireMfa::class]);

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
