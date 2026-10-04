<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use UnitEnum;

class AdminDashboard extends Dashboard
{
    protected static string|UnitEnum|null $navigationGroup = 'Основное';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    public function getTitle(): string
    {
        return __('filament.dashboard.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.dashboard.label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.main');
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 2,
        ];
    }
}
