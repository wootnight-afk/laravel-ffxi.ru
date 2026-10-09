@extends('layouts.app')

@section('title', 'Галерея')

@section('content')
    <h1 class="page-title">Галерея</h1>

    @if ($albums->isEmpty())
        <div class="content-text">
            <p>Пока альбомов нет. Заходите позже.</p>
        </div>
    @else
        @foreach ($albums as $album)
            <h2 class="section-title">
                <a href="{{ route('gallery.album', $album->slug) }}">{{ $album->title }}</a>
            </h2>

            @if ($album->photos->isEmpty())
                <p class="news-meta">В этом разделе пока нет фотографий.</p>
            @else
                <div class="gallery-grid">
                    @foreach ($album->photos as $photo)
                        <div class="gallery-item">
                            <a href="{{ route('gallery.photo', [$album->slug, $photo->id]) }}">
                                <img
                                    src="{{ $photo->urlThumb() }}"
                                    alt="{{ $photo->caption ?: 'Фото' }}"
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
@endsection
