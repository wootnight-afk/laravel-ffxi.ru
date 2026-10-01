<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            SettingsSeeder::class,
            UserRankSeeder::class,
            EventTypeSeeder::class,
            DashboardWidgetSeeder::class,
        ]);
    }
}
