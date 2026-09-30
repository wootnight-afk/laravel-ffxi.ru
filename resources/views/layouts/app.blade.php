<!DOCTYPE html>
<html lang="ru" x-data="themeToggle()" x-bind:data-theme="theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FFXI Phoenix Server')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<header>
    <div class="nav-container">
        <a href="{{ route('home') }}" class="logo-link">
            <div class="img-placeholder" style="width: 130px; height: 40px; border: none; font-size: 10px; color: #fff; background: transparent;">FINAL FANTASY XI</div>
        </a>

        <nav class="nav-links">
            <a href="{{ route('news.index') }}" class="{{ request()->routeIs('news.*') ? 'active' : '' }}">Новости игры</a>
            <a href="{{ route('gallery.index') }}" class="{{ request()->routeIs('gallery.*') ? 'active' : '' }}">Галерея</a>
            <a href="{{ route('players.dashboard') }}" class="{{ request()->routeIs('players.*') ? 'active' : '' }}">Игроки</a>
            <a href="{{ route('contacts') }}" class="{{ request()->routeIs('contacts') ? 'active' : '' }}">Контакты</a>
        </nav>

        <div class="header-right">
            <button type="button" class="theme-toggle" x-on:click="toggle()" aria-label="Переключить тему">
                <span x-text="theme === 'dark' ? '🌙' : '☀'"></span>
            </button>

            @auth
                <a href="{{ route('cabinet.profile') }}" class="login-link">{{ auth()->user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="login-link" style="background: none; border: none; cursor: pointer; font: inherit;">Выход</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="login-link {{ request()->routeIs('login') ? 'active' : '' }}">Авторизация</a>
            @endauth
        </div>
    </div>
</header>

<div class="container">
    @yield('content')
</div>

<footer>
    <div class="footer-content">
        <div class="footer-left">
            Community of Players Final Fantasy XI - Phoenix Server
        </div>
        <div class="footer-links">
            <a href="{{ route('contacts') }}">Контакты</a>
            <a href="{{ route('login') }}">Авторизация</a>
        </div>
    </div>
</footer>

@if (session('status'))
    <div class="form-status" style="position: fixed; top: 90px; left: 16px; right: 16px; max-width: 600px; margin: 0 auto; z-index: 1002;">
        {{ session('status') }}
    </div>
@endif

<script>
    function themeToggle() {
        return {
            theme: localStorage.getItem('ffxi-theme') ||
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
            toggle() {
                this.theme = this.theme === 'dark' ? 'light' : 'dark';
                localStorage.setItem('ffxi-theme', this.theme);
            }
        };
    }
</script>

@stack('scripts')
</body>
</html>
