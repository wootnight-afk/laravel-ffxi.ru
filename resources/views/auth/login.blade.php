@extends('layouts.app')

@section('title', 'Авторизация')

@section('content')
    <h1 class="page-title">Авторизация</h1>

    @if (session('status'))
        <div class="form-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                value="{{ old('email') }}"
                required
                autofocus
            >
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                required
            >
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                <input type="checkbox" name="remember" value="1">
                Запомнить меня
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Войти</button>

        <div style="margin-top: 16px; font-size: 13px;">
            <a href="{{ route('password.request') }}">Забыли свой пароль?</a>
            <p style="color: var(--text-muted); margin-top: 4px;">
                Следуйте на форму для запроса пароля.<br>
                После получения контрольной строки — на форму для смены пароля.
            </p>
        </div>

        @if (Route::has('register'))
            <div style="margin-top: 16px; font-size: 13px;">
                Нет аккаунта? <a href="{{ route('register') }}">Создать аккаунт</a>
            </div>
        @endif
    </form>
@endsection
