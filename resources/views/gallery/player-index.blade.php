@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <h1 class="page-title">{{ $user->name }} — {{ __('site.gallery.title') }}</h1>

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
                <a href="{{ route('gallery.player.album', [$user->name, $album->slug]) }}">{{ $albumTitle }}</a>
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
                            {{-- ?photo=<id> opens the album with this photo in the
                                 lightbox, exactly like the site gallery overview. --}}
                            <a href="{{ route('gallery.player.album', ['user' => $user->name, 'album' => $album->slug, 'photo' => $photo->id]) }}">
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

    <div style="margin-top: 32px;">
        <a href="{{ route('players.show', $user->name) }}">← {{ $user->name }}</a>
    </div>
@endsection
