@php
    $hideBanner = request()->cookie('cookie_banner_ack') === '1';
@endphp

@if (! $hideBanner)
    <div class="cookie-banner" data-cookie-banner>
        <div>
            Мы используем технические cookie для работы сайта
            (сессия, тема оформления, идентификатор гостя — 30 дней).
            Данные не передаются третьим лицам.
            Подробнее — <a href="{{ route('cookie') }}">Cookie</a>
            и <a href="{{ route('privacy') }}">Политика ПД</a>.
        </div>
        <button type="button" class="btn btn-primary" data-cookie-banner-dismiss>Понятно</button>
    </div>
@endif
