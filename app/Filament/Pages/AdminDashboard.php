<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use UnitEnum;

class AdminDashboard extends Dashboard
{
    protected static string|UnitEnum|null $navigationGroup = 'Основное';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Панель управления';

    protected static ?string $navigationLabel = 'Дашборд';

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 2,
        ];
    }
}
