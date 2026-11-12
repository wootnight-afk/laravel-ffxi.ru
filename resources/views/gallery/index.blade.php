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
                @php
                    $albumTitle = app()->getLocale() === \App\Enums\Locale::En->value && filled($album->title_en)
                        ? $album->title_en
                        : $album->title;
                @endphp
                <h2 class="section-title">
                    <a href="{{ route('gallery.album', $album->slug) }}">{{ $albumTitle }}</a>
                </h2>

                @if ($album->photos->isEmpty())
                    <p class="news-meta">{{ __('site.gallery.album_empty') }}</p>
                @else
                    <div class="gallery-grid">
                        @foreach ($album->photos as $photo)
                            @php
                                $photoCaption = app()->getLocale() === \App\Enums\Locale::En->value && filled($photo->caption_en)
                                    ? $photo->caption_en
                                    : $photo->caption;
                            @endphp
                            <div class="gallery-item">
                                {{-- ?photo=<id> is the deep link: the album page opens
                                     PhotoSwipe on exactly this photo. --}}
                                <a href="{{ route('gallery.album', ['album' => $album->slug, 'photo' => $photo->id]) }}">
                                    <img
                                        src="{{ $photo->urlThumb() }}"
                                        alt="{{ $photoCaption ?: __('site.gallery.photo_alt') }}"
                                        class="gallery-thumb"
                                        loading="lazy"
                                    >
                                </a>
                                @if ($photoCaption)
                                    <div class="gallery-caption">{{ $photoCaption }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endif
    @endif
@endsection
