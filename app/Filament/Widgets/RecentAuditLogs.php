<?php

namespace App\Filament\Widgets;

use App\Models\AdminAuditLog;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class RecentAuditLogs extends Widget
{
    protected string $view = 'filament.widgets.recent-audit-logs';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'entries' => AdminAuditLog::query()
                ->with('user')
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ];
    }

    public function render(): View
    {
        return view($this->view, $this->getViewData());
    }
}
