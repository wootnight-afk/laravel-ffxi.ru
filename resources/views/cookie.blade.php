@extends('layouts.app')

@section('title', 'Использование cookie')

@section('content')
    <h1 class="page-title">Использование cookie</h1>

    <div class="content-text">
        <p>Сайт использует <strong>только технические cookie</strong>, необходимые для его работы. Мы не используем сторонние системы аналитики, рекламные трекеры и не передаём данные третьим лицам.</p>

        <h2 class="section-title">Список cookie</h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                    <th style="padding: 8px;">Cookie</th>
                    <th style="padding: 8px;">Назначение</th>
                    <th style="padding: 8px;">Срок</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 8px;"><code>ffxi-session</code></td>
                    <td style="padding: 8px;">Сессия пользователя</td>
                    <td style="padding: 8px;">2 часа</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 8px;"><code>XSRF-TOKEN</code></td>
                    <td style="padding: 8px;">Защита от CSRF-атак</td>
                    <td style="padding: 8px;">2 часа</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 8px;"><code>guest_uid</code></td>
                    <td style="padding: 8px;">Анонимный идентификатор гостя (для внутренней статистики)</td>
                    <td style="padding: 8px;">30 дней</td>
                </tr>
            </tbody>
        </table>

        <h2 class="section-title">Локальное хранилище</h2>
        <p>В <code>localStorage</code> браузера сохраняется только выбранная тема оформления (<code>ffxi-theme</code>). Эти данные не передаются на сервер.</p>

        <p>Подробнее об обработке персональных данных — в <a href="{{ route('privacy') }}">Политике обработки персональных данных</a>.</p>
    </div>
@endsection
