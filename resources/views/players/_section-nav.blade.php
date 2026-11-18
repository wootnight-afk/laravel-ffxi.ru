{{--
    Левое вертикальное меню раздела «Игроки» (каркас по docs/design/designplayersreview.html).

    Это ДОПОЛНИТЕЛЬНАЯ навигация: она повторяет структуру прототипа
    (бренд → группы пунктов → футер) и не заменяет существующее меню
    players/_sidebar.blade.php.

    Пункт выводится только если его маршрут зарегистрирован, поэтому ссылок
    на несуществующие страницы здесь быть не может. Иконки — инлайновые SVG
    из прототипа (stroke=currentColor), цвета берутся из CSS-переменных сайта.
--}}
@php
    $groups = [
        [
            'label' => __('site.players.section_nav.group_community'),
            'items' => [
                [
                    'route' => 'players.dashboard',
                    'active' => ['players.dashboard'],
                    'label' => __('site.players.section_nav.dashboard'),
                    'icon' => 'dashboard',
                ],
                [
                    'route' => 'players.directory',
                    'active' => ['players.directory', 'players.show', 'gallery.player.*'],
                    'label' => __('site.players.section_nav.players'),
                    'icon' => 'players',
                ],
                [
                    'route' => 'news.index',
                    'active' => ['news.*'],
                    'label' => __('site.players.section_nav.news'),
                    'icon' => 'news',
                ],
                [
                    'route' => 'events.index',
                    'active' => ['events.*'],
                    'label' => __('site.players.section_nav.events'),
                    'icon' => 'events',
                ],
                [
                    'route' => 'gallery.index',
                    'active' => ['gallery.*'],
                    'label' => __('site.players.section_nav.gallery'),
                    'icon' => 'gallery',
                ],
            ],
        ],
        [
            'label' => __('site.players.section_nav.group_account'),
            'items' => [
                [
                    'route' => 'cabinet.show',
                    'active' => ['cabinet.*'],
                    'label' => __('site.players.section_nav.cabinet'),
                    'icon' => 'cabinet',
                ],
            ],
        ],
    ];

    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
        'players' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'news' => '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/>',
        'events' => '<path d="M21.5 2v6h-6"/><path d="M2.5 22v-6h6"/><path d="M2 11.5a10 10 0 0 1 18.8-4.3"/><path d="M22 12.5a10 10 0 0 1-18.8 4.2"/>',
        'gallery' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.5-3.5a2 2 0 0 0-2.8 0L6 21"/>',
        'characters' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'cabinet' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    ];
@endphp

<nav class="players-section-nav" aria-label="{{ __('site.players.section_nav.label') }}">
    <div class="players-section-nav__brand">
        <span class="players-section-nav__crest" aria-hidden="true">F</span>
        <span class="players-section-nav__brand-text">
            <span class="players-section-nav__brand-name">{{ __('site.players.section_nav.brand') }}</span>
            <span class="players-section-nav__brand-sub">{{ __('site.players.section_nav.brand_sub') }}</span>
        </span>
    </div>

    @foreach ($groups as $group)
        @php($visibleItems = array_filter($group['items'], fn ($item) => \Illuminate\Support\Facades\Route::has($item['route'])))
        @continue(empty($visibleItems))

        <div class="players-section-nav__label">{{ $group['label'] }}</div>

        <ul class="players-section-nav__list">
            @foreach ($visibleItems as $item)
                @php($isActive = request()->routeIs(...$item['active']))
                <li>
                    <a
                        href="{{ route($item['route']) }}"
                        class="players-section-nav__link{{ $isActive ? ' is-active' : '' }}"
                        @if ($isActive) aria-current="page" @endif
                    >
                        <svg class="players-section-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$item['icon']] !!}</svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endforeach

    <div class="players-section-nav__footer">
        <span class="players-section-nav__pill">
            <span class="players-section-nav__dot" aria-hidden="true"></span>
            {{ __('site.players.section_nav.footer') }}
        </span>
    </div>
</nav>
