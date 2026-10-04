<?php

namespace App\Filament\Widgets;

use App\Enums\UserStatus;
use App\Models\Comment;
use App\Models\Event;
use App\Models\GuestVisitor;
use App\Models\News;
use App\Models\Photo;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminDashboardStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'editor']) ?? false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $pendingComments = Comment::query()
            ->where(function ($query) {
                $query->where('status', Comment::STATUS_PENDING)
                    ->orWhere('is_reported', true);
            })
            ->count();

        $siteNews = News::query()->where('scope', News::SCOPE_SITE);
        $sitePhotos = Photo::query()->whereHas('album', fn ($query) => $query->where('scope', 'site'));
        $thisWeekEvents = Event::query()
            ->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()]);

        if (auth()->user()?->hasRole('editor')) {
            return [
                Stat::make('Новости сайта', (clone $siteNews)->count()),
                Stat::make('Комментарии на модерации', $pendingComments),
                Stat::make('Фотографии сайта', $sitePhotos->count()),
                Stat::make('События на этой неделе', $thisWeekEvents->count()),
            ];
        }

        return [
            Stat::make('Пользователи', User::query()->count()),
            Stat::make('Новые пользователи за 7 дней', User::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->count()),
            Stat::make('Новости', News::query()->count()),
            Stat::make('Комментарии на модерации', $pendingComments),
            Stat::make('Фотографии', Photo::query()->count()),
            Stat::make('Гости онлайн', GuestVisitor::query()
                ->whereNull('converted_user_id')
                ->where('last_seen_at', '>=', now()->subMinutes(5))
                ->count()),
            Stat::make('События на этой неделе', $thisWeekEvents->count()),
            Stat::make('Запросы на удаление', User::query()
                ->where('status', UserStatus::DeletionRequested)
                ->count()),
        ];
    }
}
