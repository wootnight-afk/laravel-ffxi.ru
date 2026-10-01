@extends('layouts.app')

@section('title', $news->title)

@section('content')
    <article class="news-item" style="border-bottom: none;">
        @if ($news->cover_path)
            <img
                src="{{ asset('storage/' . $news->cover_path) }}"
                alt="{{ $news->title }}"
                class="news-image"
            >
        @endif

        <h1 class="page-title">{{ $news->title }}</h1>

        <div class="news-meta" style="margin-bottom: 24px;">
            <span>
                @if ($news->isSite())
                    Редакция FFXI.ru
                @else
                    {{ $news->user?->name ?? 'Автор' }}
                @endif
            </span>
            <span>{{ $news->published_at?->format('d.m.Y H:i') ?? '—' }}</span>
        </div>

        <div class="content-text">
            {!! $news->body_html !!}
        </div>

        <div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid var(--border-color);">
            <a href="{{ route('news.index') }}">← Ко всем новостям</a>
        </div>
    </article>

    @if ($news->comments_enabled)
        <section style="margin-top: 48px;">
            <h2 class="section-title">Комментарии</h2>

            <x-comment-thread :comments="$news->comments" :news="$news" />
            <x-comment-form :news="$news" />
        </section>
    @endif
@endsection
