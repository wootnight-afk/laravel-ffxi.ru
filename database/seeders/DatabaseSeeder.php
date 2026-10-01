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

        // Демо-контент — только в local/testing, никогда на production.
        if (app()->environment('local', 'testing')) {
            $this->call([
                DemoContentSeeder::class,
                GalleryDemoSeeder::class,
            ]);
        }
    }
}
