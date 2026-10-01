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
            News::updateOrCreate(
                ['slug' => Str::slug($item['title'])],
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
                'show_in_menu' => true,
                'menu_order' => 10,
            ],
            [
                'title' => 'О сообществе',
                'body' => "Содружество русскоязычных игроков Final Fantasy XI — **Phoenix Server**.\n\nМы объединяем игроков из разных городов и стран, помогаем новичкам, организуем события и делимся впечатлениями.",
                'show_in_menu' => true,
                'menu_order' => 20,
            ],
        ];

        foreach ($pages as $item) {
            Page::updateOrCreate(
                ['slug' => Str::slug($item['title'])],
                [
                    'title' => $item['title'],
                    'body' => $item['body'],
                    'is_published' => true,
                    'show_in_menu' => $item['show_in_menu'],
                    'menu_order' => $item['menu_order'],
                ],
            );
        }
    }
}
