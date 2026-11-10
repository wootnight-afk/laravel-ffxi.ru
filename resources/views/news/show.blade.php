@extends('layouts.app')

@section('title', $news->title)

@section('content')
    <article class="news-item" style="border-bottom: none;">
        <h1 class="page-title">{{ $news->title }}</h1>

        @if ($news->cover_path)
            <img
                src="{{ asset('storage/' . $news->cover_path) }}"
                alt="{{ $news->title }}"
                class="news-image"
            >
        @endif

        <div class="news-meta" style="margin-bottom: 24px;">
            <span>
                @if ($news->isSite())
                    {{ __('site.news.editorial') }}
                @else
                    {{ $news->user?->name ?? __('site.news.author') }}
                @endif
            </span>
            <span>{{ $news->displayPublishedAt() }}</span>
        </div>

        <div class="content-text">
            {!! $news->body_html !!}
        </div>

        <div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid var(--border-color);">
            <a href="{{ route('news.index') }}">{{ __('site.news.back') }}</a>
        </div>
    </article>

    @if ($news->comments_enabled)
        <section style="margin-top: 48px;">
            <h2 class="section-title">{{ __('site.comments.title') }}</h2>

            <x-comment-thread :comments="$news->comments" :news="$news" />
            <x-comment-form :news="$news" />
        </section>
    @endif
@endsection
