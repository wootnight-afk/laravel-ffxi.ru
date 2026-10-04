<?php

return [
    'navigation' => [
        'main' => 'Основное',
        'community' => 'Сообщество',
        'content' => 'Контент',
        'system' => 'Система',
    ],
    'dashboard' => [
        'title' => 'Панель управления',
        'label' => 'Дашборд',
        'stats' => [
            'users' => 'Пользователи',
            'users_new_week' => 'Новые пользователи за 7 дней',
            'news' => 'Новости',
            'news_site' => 'Новости сайта',
            'comments_pending' => 'Комментарии на модерации',
            'photos' => 'Фотографии',
            'photos_site' => 'Фотографии сайта',
            'guests_online' => 'Гости онлайн',
            'events_week' => 'События на этой неделе',
            'account_requests' => 'Запросы пользователей',
        ],
        'charts' => [
            'registrations_heading' => 'Регистрации за 30 дней',
            'registrations_label' => 'Регистрации',
        ],
        'audit' => [
            'heading' => 'Последние административные действия',
            'date' => 'Дата',
            'user' => 'Пользователь',
            'action' => 'Действие',
            'object' => 'Объект',
            'empty' => 'Записей пока нет',
        ],
    ],
    'resources' => [
        'users' => [
            'label' => 'Пользователь',
            'plural' => 'Пользователи',
            'fields' => [
                'name' => 'Имя',
                'email' => 'Email',
                'roles' => 'Роли',
                'rank' => 'Ранг',
                'email_verified_at' => 'Email подтверждён',
                'status' => 'Статус',
                'account_requests' => 'Запросы пользователей',
                'request_action' => 'Действие',
                'request_reason' => 'Причина / сообщение',
                'request_date' => 'Дата запроса',
                'social_links' => 'Социальные ссылки',
                'social_type' => 'Тип',
                'username' => 'Имя пользователя',
                'url' => 'Ссылка',
                'is_visible' => 'Отображается в профиле',
            ],
            'actions' => [
                'edit' => 'Изменить',
                'restore' => 'Восстановить аккаунт',
                'restore_confirm' => 'Восстановить аккаунт без изменения его контента?',
                'restored' => 'Аккаунт восстановлен.',
            ],
        ],
        'roles' => [
            'label' => 'Роль',
            'plural' => 'Роли',
            'fields' => [
                'name' => 'Роль',
                'guard' => 'Guard',
                'permissions' => 'Разрешения',
            ],
        ],
        'guests' => [
            'label' => 'Гость',
            'plural' => 'Гости',
            'fields' => [
                'display_name' => 'Имя гостя',
                'hits' => 'Посещения',
                'first_seen_at' => 'Первое посещение',
                'last_seen_at' => 'Последнее посещение',
                'converted_user' => 'Зарегистрированный пользователь',
            ],
        ],
    ],
    'user_status' => [
        'active' => 'Активен',
        'deletion_requested' => 'Запрос на удаление',
        'suspended' => 'Приостановлен',
    ],
];
