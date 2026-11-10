@extends('layouts.app')

@section('title', $galleryPage['metaTitle'])
@section('metaDescription', $galleryPage['metaDescription'] ?? '')

@section('content')
    <h1 class="page-title">{{ $galleryPage['h1'] }}</h1>

    @if ($galleryPage['bodyHtml'] !== '')
        <div class="content-text">
            {!! $galleryPage['bodyHtml'] !!}
        </div>
    @endif

    @if ($galleryPage['galleryComponentEnabled'])
        @if ($albums->isEmpty())
            <div class="content-text">
                <p>{{ __('site.gallery.empty_albums') }}</p>
            </div>
        @else
            @foreach ($albums as $album)
                <h2 class="section-title">
                    <a href="{{ route('gallery.album', $album->slug) }}">{{ $album->title }}</a>
                </h2>

                @if ($album->photos->isEmpty())
                    <p class="news-meta">{{ __('site.gallery.album_empty') }}</p>
                @else
                    <div class="gallery-grid">
                        @foreach ($album->photos as $photo)
                            <div class="gallery-item">
                                <a href="{{ route('gallery.photo', [$album->slug, $photo->id]) }}">
                                    <img
                                        src="{{ $photo->urlThumb() }}"
                                        alt="{{ $photo->caption ?: __('site.gallery.photo_alt') }}"
                                        class="gallery-thumb"
                                        loading="lazy"
                                    >
                                </a>
                                @if ($photo->caption)
                                    <div class="gallery-caption">{{ $photo->caption }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endif
    @endif
@endsection
