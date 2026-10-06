<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminActivityServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminActivityServiceProvider::class,
    AdminPanelProvider::class,
];
