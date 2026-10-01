@extends('layouts.app')

@section('title', 'Новости игры')

@section('content')
    <h1 class="page-title">Новости игры</h1>

    @if ($news->isEmpty())
        <div class="content-text">
            <p>Пока новостей нет. Заходите позже.</p>
        </div>
    @else
        @foreach ($news as $item)
            <article class="news-item">
                @if ($item->cover_path)
                    <img
                        src="{{ asset('storage/' . $item->cover_path) }}"
                        alt="{{ $item->title }}"
                        class="news-image"
                        loading="lazy"
                    >
                @endif

                <h3 style="font-size: 18px; margin-bottom: 8px; color: var(--primary-color);">
                    {{ $item->title }}
                </h3>

                <p class="news-text">{{ $item->excerpt }}</p>

                <div class="news-meta">
                    <span>{{ $item->published_at?->format('d.m.Y') ?? '—' }}</span>
                    <a href="{{ route('news.show', $item->slug) }}">Читать дальше →</a>
                </div>
            </article>
        @endforeach

        <div style="margin-top: 32px;">
            {{ $news->links() }}
        </div>
    @endif
@endsection
