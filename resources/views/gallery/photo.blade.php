@extends('layouts.app')

@section('title', $photo->caption ?: $album->title)

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h1 class="page-title" style="margin-bottom: 0;">{{ $album->title }}</h1>
        <a href="{{ route('gallery.album', $album->slug) }}">← К альбому</a>
    </div>

    {{-- Модальное окно с полноразмерным изображением --}}
    <div data-photo-zoom style="position: relative;">
        <div
            style="text-align: center; cursor: zoom-in; margin-bottom: 16px;"
            data-photo-zoom-open
        >
            <img
                src="{{ $photo->urlMedium() }}"
                alt="{{ $photo->caption ?: 'Фото' }}"
                style="max-width: 100%; height: auto; border-radius: 4px;"
            >
        </div>

        <div
            data-photo-zoom-overlay
            hidden
            style="position: fixed; inset: 0; background: rgba(0,0,0,0.92); z-index: 9999; display: flex; align-items: center; justify-content: center; cursor: zoom-out;"
        >
            <img
                src="{{ $photo->urlOriginal() }}"
                alt="{{ $photo->caption ?: 'Фото' }}"
                style="max-width: 95vw; max-height: 95vh;"
            >
        </div>
    </div>

    @if ($photo->caption)
        <p style="font-size: 14px; margin-bottom: 8px;">{{ $photo->caption }}</p>
    @endif

    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 24px;">
        @if ($photo->taken_at)
            Снято: {{ $photo->taken_at->format('d.m.Y') }}
        @endif
        @if ($photo->width && $photo->height)
            · {{ $photo->width }}×{{ $photo->height }}
        @endif
    </div>

    {{-- Навигация prev/next --}}
    <div style="display: flex; justify-content: space-between; margin-bottom: 32px;">
        <div>
            @if ($prevPhoto)
                <a href="{{ route('gallery.photo', [$album->slug, $prevPhoto->id]) }}">← Предыдущее</a>
            @endif
        </div>
        <div>
            @if ($nextPhoto)
                <a href="{{ route('gallery.photo', [$album->slug, $nextPhoto->id]) }}">Следующее →</a>
            @endif
        </div>
    </div>

    @if ($photo->album->isSite())
        <section style="margin-top: 48px;">
            <h2 class="section-title">Комментарии</h2>

            @auth
                <p style="font-size: 13px; color: var(--text-muted);">
                    Форма комментариев к фото появится в следующем обновлении.
                </p>
            @else
                <p style="font-size: 13px; color: var(--text-muted);">
                    <a href="{{ route('login') }}">Войдите</a>, чтобы оставить комментарий.
                </p>
            @endauth
        </section>
    @endif
@endsection
