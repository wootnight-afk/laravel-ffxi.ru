@extends('layouts.app')

@section('title', $newsPage['metaTitle'])
@section('metaDescription', $newsPage['metaDescription'] ?? '')

@section('content')
    <h1 class="page-title">{{ $newsPage['h1'] }}</h1>

    <img class="home-image" src="{{ $newsPage['mainImageUrl'] }}" alt="{{ $newsPage['mainImageAlt'] }}" width="{{ $newsPage['mainImageWidth'] }}" height="{{ $newsPage['mainImageHeight'] }}">

    @if ($newsPage['bodyHtml'] !== '')
        <div class="content-text">
            {!! $newsPage['bodyHtml'] !!}
        </div>
    @endif

    @if ($newsPage['newsComponentEnabled'])
        <hr class="divider">

        @if ($news->isEmpty())
            <div class="content-text">
                <p>{{ __('site.news.empty') }}</p>
            </div>
        @else
            @foreach ($news as $item)
                <article class="news-item">
                    <h3 style="font-size: 18px; margin-bottom: 8px; color: var(--primary-color);">
                        {{ $item->title }}
                    </h3>

                    <p class="news-text">{{ $item->excerpt }}</p>

                    <div class="news-meta">
                        <span>{{ $item->displayPublishedAt() }}</span>
                        <a href="{{ route('news.show', $item->slug) }}">{{ __('site.news.read_more') }}</a>
                    </div>
                </article>
            @endforeach

            <div style="margin-top: 32px;">
                {{ $news->links() }}
            </div>
        @endif
    @endif
@endsection
