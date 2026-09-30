@php
    $hideBanner = request()->cookie('cookie_banner_ack') === '1';
@endphp

@if (! $hideBanner)
    <div
        class="cookie-banner"
        x-data="{ ack() { document.cookie = 'cookie_banner_ack=1; path=/; max-age=31536000; samesite=lax'; this.$el.remove(); } }"
    >
        <div>
            Мы используем технические cookie для работы сайта
            (сессия, тема оформления, идентификатор гостя — 30 дней).
            Данные не передаются третьим лицам.
            Подробнее — <a href="{{ route('cookie') }}">Cookie</a>
            и <a href="{{ route('privacy') }}">Политика ПД</a>.
        </div>
        <button type="button" class="btn btn-primary" x-on:click="ack()">Понятно</button>
    </div>
@endif
