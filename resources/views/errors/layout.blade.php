<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        // Anti-FOUC: apply the stored theme before the body renders.
        // Kept inline so error pages do not depend on Livewire or the DB.
        (function () {
            try {
                var stored = localStorage.getItem('ffxi-theme');
                var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                // localStorage unavailable — keep the default light theme.
            }
        })();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title') — FFXI Phoenix Server</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<header>
    <div class="nav-container">
        <a href="{{ route('home') }}" class="logo-link">
            <span class="logo-wrap">
                <img src="{{ asset('img/bx_default_logo.webp') }}" alt="Final Fantasy XI">
            </span>
        </a>

        <div class="header-right">
            <x-language-switcher />
        </div>
    </div>
</header>

<main class="container">
    <div class="error-page">
        <p class="error-page__code">@yield('code')</p>
        <h1 class="page-title">@yield('title')</h1>
        <p class="error-page__message">@yield('message')</p>
        <div class="error-page__actions">
            @yield('actions')
        </div>
    </div>
</main>

<footer>
    <div class="footer-content">
        <div class="footer-left">
            {{ __('site.footer.tagline') }}
        </div>
        <div class="footer-links">
            <a href="{{ route('contacts') }}">{{ __('site.footer.contacts') }}</a>
            <a href="{{ route('home') }}">{{ __('site.errors.home') }}</a>
        </div>
    </div>
</footer>

</body>
</html>
