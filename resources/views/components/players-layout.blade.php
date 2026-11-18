{{--
    Общая обёртка раздела «Игроки»: заголовок, левое меню и контент страницы.

    Каркас повторяет docs/design/designplayersreview.html: слева вертикальная
    навигационная колонка (players/_section-nav), справа рабочая область.
    Существующее меню разделов (players/_sidebar) сохранено без изменений
    и выводится над контентом.

    Использование:
        <x-players-layout :title="__('site.players.overview.title')">
            ...контент...
        </x-players-layout>
--}}
@props(['title' => null])

{{-- Каркас растянут на всю высоту между фиксированным header и sticky footer:
     колонка меню не прокручивается, прокручивается только рабочая область. --}}
<div class="players-shell">
    <aside class="players-shell__nav">
        <div class="players-shell__nav-inner">
            @include('players._section-nav')
        </div>
    </aside>

    <div class="players-shell__main">
        <div class="players-shell__content">
            <h1 class="page-title">{{ $title ?? __('site.players.title') }}</h1>

            <div class="players-layout">
                <aside class="players-layout__aside">
                    @include('players._sidebar')
                </aside>

                <div class="players-layout__content">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
