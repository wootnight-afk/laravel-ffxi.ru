@extends('layouts.app')

@section('title', 'Галерея')

@section('content')
    <h1 class="page-title">Галерея</h1>

    @if ($albums->isEmpty())
        <div class="content-text">
            <p>Пока альбомов нет. Заходите позже.</p>
        </div>
    @else
        <div class="gallery-grid">
            @foreach ($albums as $album)
                <div class="gallery-item">
                    <a href="{{ route('gallery.album', $album->slug) }}" style="text-decoration: none;">
                        @if ($album->coverPhoto)
                            <img
                                src="{{ $album->coverPhoto->urlThumb() }}"
                                alt="{{ $album->title }}"
                                class="gallery-thumb"
                                loading="lazy"
                            >
                        @else
                            <div class="img-placeholder gallery-thumb">Нет фото</div>
                        @endif

                        <div class="gallery-caption">
                            {{ $album->title }}
                            <span style="color: var(--text-muted); font-size: 12px;">
                                ({{ $album->photos_count }})
                            </span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
