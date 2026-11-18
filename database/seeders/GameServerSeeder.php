<?php

namespace Database\Seeders;

use App\Models\GameServer;
use Illuminate\Database\Seeder;

/**
 * Initial game server reference rows (ADR-012 §2.1).
 *
 * The list is data, not code: admins add and deactivate servers through the
 * database, and this seeder only provides the starting set.
 */
class GameServerSeeder extends Seeder
{
    public function run(): void
    {
        $servers = [
            ['name' => 'Phoenix', 'slug' => 'phoenix', 'sort_order' => 10],
            ['name' => 'Asura', 'slug' => 'asura', 'sort_order' => 20],
            ['name' => 'Bahamut', 'slug' => 'bahamut', 'sort_order' => 30],
            ['name' => 'Carbuncle', 'slug' => 'carbuncle', 'sort_order' => 40],
            ['name' => 'Fenrir', 'slug' => 'fenrir', 'sort_order' => 50],
            ['name' => 'Lakshmi', 'slug' => 'lakshmi', 'sort_order' => 60],
            ['name' => 'Leviathan', 'slug' => 'leviathan', 'sort_order' => 70],
            ['name' => 'Odin', 'slug' => 'odin', 'sort_order' => 80],
            ['name' => 'Ragnarok', 'slug' => 'ragnarok', 'sort_order' => 90],
            ['name' => 'Shiva', 'slug' => 'shiva', 'sort_order' => 100],
            ['name' => 'Siren', 'slug' => 'siren', 'sort_order' => 110],
            ['name' => 'Valefor', 'slug' => 'valefor', 'sort_order' => 120],
        ];

        foreach ($servers as $server) {
            GameServer::query()->updateOrCreate(
                ['slug' => $server['slug']],
                $server + ['is_active' => true],
            );
        }
    }
}
