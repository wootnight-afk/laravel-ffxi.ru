<?php

return [
    'min' => 3,
    'max' => 24,
    'regex' => '/^[a-zA-Z][a-zA-Z0-9_-]*$/',

    'blacklist' => [
        'admin', 'administrator',
        'guest', 'guests',
        'login', 'logout', 'register', 'registration',
        'password', 'email', 'verify', 'verification',
        'user', 'users', 'player', 'players', 'profile', 'profiles',
        'rank', 'ranks', 'role', 'roles',
        'news', 'gallery', 'events', 'event', 'album', 'albums',
        'page', 'pages', 'cabinet', 'activity', 'chat',
        'directory', 'members', 'community',
        'api', 'system', 'storage', 'public', 'static', 'assets', 'img',
        'cookie', 'privacy', 'policy',
        'moderator', 'mod', 'root', 'support', 'help', 'about', 'contacts',
        'test', 'demo', 'null', 'undefined', 'anonymous', 'anon',
    ],

    'suggestions' => 3,
    'suggestion_digit_min' => 2,
    'suggestion_digit_max' => 4,
];
