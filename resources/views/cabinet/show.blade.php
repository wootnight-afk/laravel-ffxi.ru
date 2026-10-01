@extends('layouts.app')

@section('title', 'Мой кабинет')

@section('content')
    <h1 class="page-title">Мой кабинет</h1>

    <div style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">
        {{-- Сайдбар с табами --}}
        <nav style="min-width: 220px; flex-shrink: 0;">
            <ul style="list-style: none; padding: 0; margin: 0; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                @php
                    $tabs = [
                        'profile' => 'Профиль',
                        'social' => 'Социальные сети',
                        'news' => 'Мои новости',
                        'gallery' => 'Моя галерея',
                        'events' => 'Мои события',
                        'security' => 'Безопасность',
                        'danger' => 'Опасная зона',
                    ];
                @endphp

                @foreach ($tabs as $key => $label)
                    <li>
                        <a
                            href="{{ route('cabinet.tab', ['tab' => $key]) }}"
                            style="display: block; padding: 10px 16px; text-decoration: none; font-size: 14px; border-bottom: 1px solid var(--border-color); background: {{ $tab === $key ? 'var(--bg-light)' : 'transparent' }}; color: {{ $tab === $key ? 'var(--primary-color)' : 'var(--text-main)' }}; font-weight: {{ $tab === $key ? '600' : '400' }};"
                        >
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Контент таба --}}
        <div style="flex: 1; min-width: 0;">
            @if ($tab === 'profile')
                @include('cabinet.tabs.profile', ['user' => $user])
            @elseif ($tab === 'social')
                @include('cabinet.tabs.social', ['user' => $user])
            @else
                <div class="content-text">
                    <h2 class="section-title">{{ $tabs[$tab] }}</h2>
                    <p style="color: var(--text-muted);">Раздел в разработке.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
