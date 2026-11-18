@extends('layouts.app')

@section('title', __('site.cabinet.title'))

@section('content')
    <h1 class="page-title">{{ __('site.cabinet.title') }}</h1>

    <div style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">
        {{-- Сайдбар с табами --}}
        <nav style="min-width: 220px; flex-shrink: 0;">
            <ul style="list-style: none; padding: 0; margin: 0; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                @php
                    // Character management is a cabinet section with its own
                    // routes, but it sits in the same navigation right after the
                    // profile (STAGE-1 §2.3). The item is hidden when the account
                    // has no access to the Players section, so it never leads to
                    // a 403.
                    $tabs = [
                        'profile' => ['label' => __('site.cabinet.tabs.profile'), 'route' => route('cabinet.tab', ['tab' => 'profile'])],
                        'characters' => ['label' => __('site.cabinet.characters.nav'), 'route' => route('cabinet.characters.index')],
                        'social' => ['label' => __('site.cabinet.tabs.social'), 'route' => route('cabinet.tab', ['tab' => 'social'])],
                        'news' => ['label' => __('site.cabinet.tabs.news'), 'route' => route('cabinet.tab', ['tab' => 'news'])],
                        'gallery' => ['label' => __('site.cabinet.tabs.gallery'), 'route' => route('cabinet.tab', ['tab' => 'gallery'])],
                        'events' => ['label' => __('site.cabinet.tabs.events'), 'route' => route('cabinet.tab', ['tab' => 'events'])],
                        'security' => ['label' => __('site.cabinet.tabs.security'), 'route' => route('cabinet.tab', ['tab' => 'security'])],
                        'danger' => ['label' => __('site.cabinet.tabs.danger'), 'route' => route('cabinet.tab', ['tab' => 'danger'])],
                    ];

                    if (! auth()->user()?->can('section.players.view') || ! \Illuminate\Support\Facades\Route::has('cabinet.characters.index')) {
                        unset($tabs['characters']);
                    }
                @endphp

                @foreach ($tabs as $key => $item)
                    @php($isActive = $key === 'characters' ? request()->routeIs('cabinet.characters.*') : $tab === $key)
                    <li>
                        <a
                            href="{{ $item['route'] }}"
                            style="display: block; padding: 10px 16px; text-decoration: none; font-size: 14px; border-bottom: 1px solid var(--border-color); background: {{ $isActive ? 'var(--bg-light)' : 'transparent' }}; color: {{ $isActive ? 'var(--primary-color)' : 'var(--text-main)' }}; font-weight: {{ $isActive ? '600' : '400' }};"
                        >
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Контент таба --}}
        <div style="flex: 1; min-width: 0;">
            @if ($tab === 'profile')
                @include('cabinet.tabs.profile', ['user' => $user, 'timezoneOptions' => $timezoneOptions])
            @elseif ($tab === 'social')
                @include('cabinet.tabs.social', ['user' => $user])
            @elseif ($tab === 'news')
                @include('cabinet.tabs.my-news', ['user' => $user])
            @elseif ($tab === 'gallery')
                @include('cabinet.tabs.my-gallery', ['user' => $user])
            @elseif ($tab === 'security')
                @include('cabinet.tabs.security', ['user' => $user])
            @elseif ($tab === 'danger')
                @include('cabinet.tabs.danger', ['user' => $user])
            @else
                <div class="content-text">
                    <h2 class="section-title">{{ $tabs[$tab]['label'] ?? __('site.cabinet.title') }}</h2>
                    <p style="color: var(--text-muted);">{{ __('site.cabinet.in_development') }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
