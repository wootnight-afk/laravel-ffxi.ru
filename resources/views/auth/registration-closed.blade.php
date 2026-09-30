@extends('layouts.app')

@section('title', 'Регистрация временно приостановлена')

@section('content')
    <h1 class="page-title">Регистрация временно приостановлена</h1>

    <div class="content-text" style="max-width: 600px;">
        <p>Регистрация новых пользователей временно приостановлена. Сайт работает в режиме информационного ресурса.</p>
        <p>Если у вас есть вопросы или вы хотите получить доступ к сообществу — напишите нам:</p>
        <p><a href="mailto:linkshell@yandex.ru">linkshell@yandex.ru</a></p>
        <p style="margin-top: 24px;">
            <a href="{{ route('home') }}">← Вернуться на главную</a>
        </p>
    </div>
@endsection
