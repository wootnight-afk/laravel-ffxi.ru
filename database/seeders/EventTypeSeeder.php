<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Таблицы event_types ещё нет — она появится на этапе 7 (Дашборд).
        // Сидер существует, но подключается позже. Пока — заглушка, чтобы
        // DatabaseSeeder не падал.
        if (! \Schema::hasTable('event_types')) {
            return;
        }

        $types = [
            ['key' => 'party', 'title' => 'Party', 'icon' => '👥', 'sort_order' => 10],
            ['key' => 'raid', 'title' => 'Raid', 'icon' => '⚔', 'sort_order' => 20],
            ['key' => 'mission', 'title' => 'Mission', 'icon' => '📜', 'sort_order' => 30],
            ['key' => 'quest', 'title' => 'Quest', 'icon' => '🗺', 'sort_order' => 40],
            ['key' => 'farm', 'title' => 'Farm', 'icon' => '💰', 'sort_order' => 50],
            ['key' => 'newbie_help', 'title' => 'Помощь новичкам', 'icon' => '🤝', 'sort_order' => 60],
            ['key' => 'screenshot', 'title' => 'Screenshot', 'icon' => '📷', 'sort_order' => 70],
            ['key' => 'community', 'title' => 'Community', 'icon' => '🎉', 'sort_order' => 80],
            ['key' => 'announcement', 'title' => 'Announcement', 'icon' => '📢', 'sort_order' => 90],
            ['key' => 'recruiting', 'title' => 'Recruiting', 'icon' => '🧭', 'sort_order' => 100],
            ['key' => 'other', 'title' => 'Другое', 'icon' => '❓', 'sort_order' => 999],
        ];

        foreach ($types as $type) {
            DB::table('event_types')->updateOrInsert(
                ['key' => $type['key']],
                $type + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
