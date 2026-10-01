<?php

return [
    'max_links_per_user' => 10,

    'platforms' => [
        'discord' => [
            'title' => 'Discord',
            'icon' => '💬',
            'url_template' => null,
        ],
        'telegram' => [
            'title' => 'Telegram',
            'icon' => '📨',
            'url_template' => 'https://t.me/{username}',
        ],
        'vk' => [
            'title' => 'VK',
            'icon' => '🔵',
            'url_template' => 'https://vk.com/{username}',
        ],
        'steam' => [
            'title' => 'Steam',
            'icon' => '🎮',
            'url_template' => 'https://steamcommunity.com/id/{username}',
        ],
        'twitch' => [
            'title' => 'Twitch',
            'icon' => '🟣',
            'url_template' => 'https://www.twitch.tv/{username}',
        ],
        'youtube' => [
            'title' => 'YouTube',
            'icon' => '🔴',
            'url_template' => 'https://www.youtube.com/@{username}',
        ],
        'github' => [
            'title' => 'GitHub',
            'icon' => '🐙',
            'url_template' => 'https://github.com/{username}',
        ],
        'x' => [
            'title' => 'X',
            'icon' => '✖',
            'url_template' => 'https://x.com/{username}',
        ],
        'psn' => [
            'title' => 'PlayStation Network',
            'icon' => '🎮',
            'url_template' => null,
        ],
        'xbox' => [
            'title' => 'Xbox',
            'icon' => '🎮',
            'url_template' => null,
        ],
        'reddit' => [
            'title' => 'Reddit',
            'icon' => '🟠',
            'url_template' => 'https://www.reddit.com/user/{username}',
        ],
        'other' => [
            'title' => 'Другое',
            'icon' => '🔗',
            'url_template' => null,
        ],
    ],
];
