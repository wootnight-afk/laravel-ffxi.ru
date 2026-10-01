@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <div style="display: flex; gap: 24px; margin-bottom: 32px; flex-wrap: wrap; align-items: flex-start;">
        <x-avatar :user="$user" :size="128" />

        <div style="flex: 1; min-width: 240px;">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px;">
                <h1 class="page-title" style="margin-bottom: 0;">{{ $user->name }}</h1>

                @if (! $hasFullAccess)
                    <span style="font-size: 12px; padding: 2px 8px; background: var(--bg-light); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-muted);">
                        Профиль закрыт
                    </span>
                @endif
            </div>

            @if ($user->rank)
                <div style="margin-bottom: 8px;">
                    <x-user-rank :user="$user" :compact="false" />
                </div>
            @endif

            <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 8px;">
                @if ($user->race)
                    Раса: {{ $user->race }}
                @endif
                @if ($user->main_job)
                    · Job: {{ $user->main_job }}
                @endif
                @if ($user->last_seen_at && $user->last_seen_at->diffInMinutes(now()) < 5)
                    · <span style="color: #15803d;">в сети</span>
                @endif
            </div>

            <div style="font-size: 13px; color: var(--text-muted);">
                Дата регистрации: {{ $user->created_at->format('d.m.Y') }}
            </div>

            @if ($isOwner)
                <div style="margin-top: 16px;">
                    <a href="{{ route('cabinet.show') }}" class="btn">Редактировать</a>
                </div>
            @endif
        </div>
    </div>

    @if (! $hasFullAccess)
        <div class="content-text">
            <p>Пользователь закрыл свой профиль для других игроков.</p>
        </div>
    @else
        @if ($user->legend_html)
            <section style="margin-bottom: 32px;">
                <h2 class="section-title">Легенда</h2>
                <div class="content-text">
                    {!! $user->legend_html !!}
                </div>
            </section>
        @endif

        <x-social-links :user="$user" />

        @if ($playerNews->isNotEmpty())
            <section style="margin-top: 32px;">
                <h2 class="section-title">Новости игрока</h2>
                @foreach ($playerNews as $item)
                    <article class="news-item">
                        <h3 style="font-size: 16px; margin-bottom: 8px;">
                            <a href="{{ route('news.show', $item->slug) }}">{{ $item->title }}</a>
                        </h3>
                        <p class="news-text">{{ $item->excerpt }}</p>
                        <div class="news-meta">
                            <span>{{ $item->published_at?->format('d.m.Y') ?? '—' }}</span>
                            <a href="{{ route('news.show', $item->slug) }}">Читать дальше →</a>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($photos->isNotEmpty())
            <section style="margin-top: 32px;">
                <h2 class="section-title">Фотографии</h2>
                <div class="gallery-grid">
                    @foreach ($photos as $photo)
                        <div class="gallery-item">
                            <a href="{{ route('gallery.photo', [$photo->album->slug, $photo->id]) }}">
                                <img
                                    src="{{ $photo->urlThumb() }}"
                                    alt="{{ $photo->caption ?: 'Фото' }}"
                                    class="gallery-thumb"
                                    loading="lazy"
                                >
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    <div style="margin-top: 32px;">
        <a href="{{ route('players.directory') }}">← К каталогу игроков</a>
    </div>
@endsection
