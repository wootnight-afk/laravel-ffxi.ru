<?php

namespace App\Providers;

use App\Events\CommentCreatedForActivity;
use App\Events\CommunityEventCreated;
use App\Events\CommunityEventJoined;
use App\Events\NewsPublishedForActivity;
use App\Events\PhotoPublishedForActivity;
use App\Events\UserRegisteredForActivity;
use App\Listeners\RecordCommentCreatedActivity;
use App\Listeners\RecordCommunityEventCreatedActivity;
use App\Listeners\RecordCommunityEventJoinedActivity;
use App\Listeners\RecordNewsPublishedActivity;
use App\Listeners\RecordPhotoPublishedActivity;
use App\Listeners\RecordUserRegisteredActivity;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

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
        EventFacade::listen(UserRegisteredForActivity::class, RecordUserRegisteredActivity::class);
        EventFacade::listen(NewsPublishedForActivity::class, RecordNewsPublishedActivity::class);
        EventFacade::listen(PhotoPublishedForActivity::class, RecordPhotoPublishedActivity::class);
        EventFacade::listen(CommunityEventCreated::class, RecordCommunityEventCreatedActivity::class);
        EventFacade::listen(CommunityEventJoined::class, RecordCommunityEventJoinedActivity::class);
        EventFacade::listen(CommentCreatedForActivity::class, RecordCommentCreatedActivity::class);

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
