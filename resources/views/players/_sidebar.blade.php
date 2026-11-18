{{--
    Левое меню раздела «Игроки».

    Пункты строятся из одного массива, поэтому добавление нового раздела
    (Linkshell, активность) не требует переработки разметки. Пункт попадает
    в меню только если его маршрут зарегистрирован — ссылок на несуществующие
    страницы здесь быть не может.
--}}
@php
    $items = [
        [
            'route' => 'players.dashboard',
            'active' => ['players.dashboard'],
            'label' => __('site.players.nav.overview'),
        ],
        [
            'route' => 'players.directory',
            'active' => ['players.directory', 'players.show', 'gallery.player.*'],
            'label' => __('site.players.nav.directory'),
        ],
    ];
@endphp

<nav class="players-nav" aria-label="{{ __('site.players.nav.label') }}">
    <ul class="players-nav__list">
        @foreach ($items as $item)
            @continue(! \Illuminate\Support\Facades\Route::has($item['route']))
            @php($isActive = request()->routeIs(...$item['active']))
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    class="players-nav__link{{ $isActive ? ' is-active' : '' }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
