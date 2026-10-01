<?php

namespace Database\Seeders;

use App\Models\DashboardWidget;
use Illuminate\Database\Seeder;

class DashboardWidgetSeeder extends Seeder
{
    public function run(): void
    {
        $widgets = [
            ['key' => 'community_chat', 'title' => 'Общий чат', 'type' => 'community_chat', 'column_span' => 2, 'sort_order' => 10],
            ['key' => 'events_board', 'title' => 'События', 'type' => 'events_board', 'column_span' => 1, 'sort_order' => 20],
            ['key' => 'activity_feed', 'title' => 'Активность', 'type' => 'activity_feed', 'column_span' => 2, 'sort_order' => 30],
            ['key' => 'online_users', 'title' => 'Онлайн', 'type' => 'online_users', 'column_span' => 1, 'sort_order' => 40],
        ];

        foreach ($widgets as $widget) {
            DashboardWidget::updateOrCreate(
                ['key' => $widget['key']],
                $widget + ['is_active' => true, 'settings' => null],
            );
        }
    }
}
