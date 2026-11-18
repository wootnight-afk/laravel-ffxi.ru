<?php

namespace Database\Seeders;

use App\Models\News;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->first();

        if ($admin === null) {
            $this->command->warn('Нет администратора — DemoContentSeeder пропущен.');

            return;
        }

        $news = [
            [
                'title' => 'MMORPG Final Fantasy XI продолжит получать обновления',
                'excerpt' => 'Как стало известно из интервью геймдиректора Ёдзи Фудзито для Dengeki Online, в 2022 году в Square Enix всерьёз обсуждали завершение поддержки проекта.',
                'body' => "Как стало известно из интервью геймдиректора Ёдзи Фудзито для **Dengeki Online**, в 2022 году в Square Enix всерьёз обсуждали завершение поддержки проекта.\n\nРассматривалась даже возможность отключения серверов ПК-версии. Причиной для таких обсуждений должен был стать финал сюжетной линии *The Voracious Resurgence*, после которого в компании ожидали снижение интереса к игре.\n\nОднако прогнозы не оправдались: сообщество продолжило активно играть и оформлять подписку.",
                'is_pinned' => true,
                'days_ago' => 1,
            ],
            [
                'title' => 'Бесплатная пробная версия — уровень повышен до 75',
                'excerpt' => 'В бесплатной пробной версии игры максимальный уровень персонажа повышен с 50 до 75, а в конце сентября будет снято ограничение по времени.',
                'body' => "В бесплатной пробной версии игры **максимальный уровень персонажа повышен с 50 до 75**, а в конце сентября будет снято ограничение по времени.\n\nПриглашайте друзей и возвращающихся игроков в Ванадиэль!",
                'is_pinned' => false,
                'days_ago' => 5,
            ],
            [
                'title' => 'Осенний фестиваль Linkshell',
                'excerpt' => 'Приглашаем всех участников сообщества на осенний фестиваль. Событие пройдёт с играми, конкурсами и наградами.',
                'body' => "Приглашаем всех участников сообщества на **осенний фестиваль**.\n\nВ программе:\n\n- игры\n- конкурсы\n- награды\n\nПодробности — в разделе События.",
                'is_pinned' => false,
                'days_ago' => 12,
            ],
        ];

        foreach ($news as $item) {
            $slug = Str::slug($item['title'], '-', 'ru');

            News::updateOrCreate(
                ['slug' => $slug],
                [
                    'user_id' => $admin->id,
                    'scope' => News::SCOPE_SITE,
                    'title' => $item['title'],
                    'excerpt' => $item['excerpt'],
                    'body' => $item['body'],
                    'is_pinned' => $item['is_pinned'],
                    'comments_enabled' => true,
                    'status' => News::STATUS_PUBLISHED,
                    'published_at' => now()->subDays($item['days_ago']),
                ],
            );
        }

        $pages = [
            [
                'title' => 'Правила сообщества',
                'body' => "## Общие правила\n\n1. Уважайте других участников.\n2. Не публикуйте материалы, нарушающие законодательство РФ.\n3. Спам и реклама запрещены.\n\nНарушение правил может привести к блокировке аккаунта.",
                // Full English version (STAGE-1 §18.3): the page never falls back
                // to the Russian text when the interface language is English.
                'title_en' => 'Community Rules',
                'body_en' => "## General rules\n\n1. Respect other members.\n2. Do not publish material that violates the law of the Russian Federation.\n3. Spam and advertising are prohibited.\n\nBreaking the rules may result in the account being blocked.",
                'meta_title_en' => 'Community Rules',
                'meta_description_en' => 'Community rules: respect for other members, no illegal content, no spam or advertising.',
                'show_in_menu' => true,
                'menu_order' => 10,
            ],
            [
                'title' => 'О сообществе',
                'body' => "Содружество русскоязычных игроков Final Fantasy XI — **Phoenix Server**.\n\nМы объединяем игроков из разных городов и стран, помогаем новичкам, организуем события и делимся впечатлениями.",
                'title_en' => 'About the Community',
                'body_en' => "A community of Russian-speaking Final Fantasy XI players — **Phoenix Server**.\n\nWe bring together players from different cities and countries, help newcomers, organise events and share our impressions.",
                'meta_title_en' => 'About the Community',
                'meta_description_en' => 'The Russian-speaking Final Fantasy XI community on Phoenix Server: who we are, how we help newcomers and which events we run.',
                'show_in_menu' => true,
                'menu_order' => 20,
            ],
        ];

        foreach ($pages as $item) {
            $slug = Str::slug($item['title'], '-', 'ru');

            Page::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $item['title'],
                    'body' => $item['body'],
                    'title_en' => $item['title_en'],
                    'body_en' => $item['body_en'],
                    'meta_title_en' => $item['meta_title_en'],
                    'meta_description_en' => $item['meta_description_en'],
                    'is_published' => true,
                    'show_in_menu' => $item['show_in_menu'],
                    'menu_order' => $item['menu_order'],
                ],
            );
        }
    }
}
