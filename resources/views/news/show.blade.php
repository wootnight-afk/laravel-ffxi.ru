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

            @if ($news->comments->isEmpty())
                <p style="color: var(--text-muted); font-size: 14px;">
                    Пока комментариев нет. Будьте первым!
                </p>
            @else
                @foreach ($news->comments as $comment)
                    <div style="padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">
                            {{ $comment->user?->name ?? '[аккаунт удалён]' }} ·
                            {{ $comment->created_at->format('d.m.Y H:i') }}
                        </div>
                        <div style="font-size: 14px; line-height: 1.6;">
                            {{ $comment->body }}
                        </div>
                    </div>
                @endforeach
            @endif

            <div style="margin-top: 16px; padding: 12px; background: var(--bg-light); border-radius: 4px; font-size: 13px; color: var(--text-muted);">
                @auth
                    Форма комментариев появится в следующем обновлении.
                @else
                    <a href="{{ route('login') }}">Войдите</a>, чтобы оставить комментарий.
                @endauth
            </div>
        </section>
    @endif
@endsection
