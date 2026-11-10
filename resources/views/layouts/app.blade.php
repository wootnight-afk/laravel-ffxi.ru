@inject('siteNavigation', \App\Services\SiteNavigation::class)
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        // Anti-FOUC: apply the stored theme before the body renders.
        // Priority: localStorage('ffxi-theme') -> prefers-color-scheme -> light.
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
    {{-- @yield('title') and @yield('metaDescription') are safe with the shorthand
         @section('name', $value) used by every view here: Blade escapes such a value
         with e() when the section is stored (Illuminate View startSection), so the
         attribute below receives data, never markup. Wrapping @yield in {{ }} would
         escape it a second time. A block-form @section or an HtmlString value would
         bypass that escape — keep these two slots shorthand and escape inside the
         block. --}}
    <title>@yield('title', 'FFXI Phoenix Server')</title>
    @hasSection('metaDescription')
        <meta name="description" content="@yield('metaDescription')">
    @elseif (! empty($metaDescription))
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<header>
    <div class="nav-container">
        @if ($siteNavigation->homeLinkVisible())
            <a href="{{ route('home') }}" class="logo-link">
        @else
            <span class="logo-link">
        @endif
                <span class="logo-wrap">
                    <img src="{{ asset('img/bx_default_logo.webp') }}" alt="Final Fantasy XI">
                </span>
        @if ($siteNavigation->homeLinkVisible())
            </a>
        @else
            </span>
        @endif

        <nav class="nav-links">
            @foreach ($siteNavigation->headerItems() as $item)
                <a href="{{ $item['url'] }}" class="{{ $item['active'] ? 'active' : '' }}">
                    @if ($item['imageUrl'])
                        <img src="{{ $item['imageUrl'] }}" alt="" class="nav-link__icon">
                    @endif
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="header-right">
            <x-language-switcher />

            <button type="button" class="theme-toggle" data-theme-toggle aria-label="{{ __('site.nav.theme_toggle') }}">
                <span class="theme-toggle__icon" data-theme-icon="light">☀</span>
                <span class="theme-toggle__icon" data-theme-icon="dark">🌙</span>
            </button>

            @auth
                @livewire('activity-bell')
                <a href="{{ route('cabinet.show') }}" class="login-link">{{ auth()->user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="login-link" style="background: none; border: none; cursor: pointer; font: inherit;">{{ __('site.nav.logout') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="login-link {{ request()->routeIs('login') ? 'active' : '' }}">{{ __('site.nav.login') }}</a>
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
            {{ $siteNavigation->footerText() }}
        </div>
        <div class="footer-links">
            @foreach ($siteNavigation->footerItems() as $item)
                <a href="{{ $item['url'] }}">
                    @if ($item['imageUrl'])
                        <img src="{{ $item['imageUrl'] }}" alt="" class="nav-link__icon">
                    @endif
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</footer>

<x-cookie-banner />

@if (session('status') && ! request()->routeIs('login'))
    <div class="form-status" style="position: fixed; top: 90px; left: 16px; right: 16px; max-width: 600px; margin: 0 auto; z-index: 1002;">
        {{ session('status') }}
    </div>
@endif

@stack('scripts')
</body>
</html>
