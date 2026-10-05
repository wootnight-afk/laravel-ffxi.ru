<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'registration_open' => false,
            'pd_policy_version' => 'draft',
            'player_news_moderation' => 'post',
            'comments_moderation' => 'reputation',
            'chat_enabled' => true,
            'chat_polling_interval' => 5,
            'chat_max_length' => 500,
            'events_enabled' => true,
            'gallery_max_albums_per_user' => 10,
            'gallery_max_photo_mb' => 8,
            'gallery_max_photos_per_day' => 50,
            'social_max_links_per_user' => 10,
            'ranks_enabled' => true,
            'default_profile_public' => false,
            'bell_enabled' => true,
            'activity_enabled' => true,
            'notifications_enabled' => true,
            'activity_retention_days' => 180,
            'admin_2fa_required' => false,
            'admin_ip_allowlist' => [],
            'admin_new_ip_notify' => true,
            'timezone_display' => 'Europe/Moscow',
            'guest_chat_enabled' => false,
            'guest_sections' => [
                'home' => true,
                'news' => true,
                'gallery' => true,
                'contacts' => true,
                'events' => true,
                'players' => false,
                'player_profiles' => false,
            ],
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()],
            );
        }
    }
}
