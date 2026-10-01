<?php

namespace Database\Seeders;

use App\Models\UserRank;
use Illuminate\Database\Seeder;

class UserRankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['key' => 'novice', 'title' => 'Новичок', 'icon' => '🌱', 'rating' => 10, 'sort_order' => 10],
            ['key' => 'adventurer', 'title' => 'Искатель приключений', 'icon' => '🗡', 'rating' => 30, 'sort_order' => 20],
            ['key' => 'veteran', 'title' => 'Ветеран', 'icon' => '🛡', 'rating' => 60, 'sort_order' => 30],
            ['key' => 'legend', 'title' => 'Легенда Phoenix', 'icon' => '👑', 'rating' => 90, 'sort_order' => 40],
        ];

        foreach ($ranks as $rank) {
            UserRank::updateOrCreate(
                ['key' => $rank['key']],
                $rank + ['is_active' => true],
            );
        }
    }
}
