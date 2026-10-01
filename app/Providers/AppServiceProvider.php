<?php

namespace App\Providers;

use App\Services\HtmlSanitizer;
use App\Services\SettingsRepository;
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
        //
    }
}
