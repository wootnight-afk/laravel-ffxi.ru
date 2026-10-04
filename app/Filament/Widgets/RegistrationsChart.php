<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class RegistrationsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Регистрации за 30 дней';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $start = now()->startOfDay()->subDays(29);
        $registrations = User::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as registration_date, COUNT(*) as registrations')
            ->groupBy('registration_date')
            ->pluck('registrations', 'registration_date');

        $labels = [];
        $counts = [];

        for ($day = 0; $day < 30; $day++) {
            $date = $start->copy()->addDays($day);
            $key = $date->toDateString();

            $labels[] = $date->translatedFormat('d M');
            $counts[] = (int) ($registrations[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Регистрации',
                    'data' => $counts,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
