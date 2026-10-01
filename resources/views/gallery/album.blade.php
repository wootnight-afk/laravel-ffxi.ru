@extends('layouts.app')

@section('title', $album->title)

@section('content')
    <h1 class="page-title">{{ $album->title }}</h1>

    @if ($album->description)
        <div class="content-text" style="margin-bottom: 16px;">
            {{ $album->description }}
        </div>
    @endif

    @if ($photos->isEmpty())
        <div class="content-text">
            <p>В этом альбоме пока нет фотографий.</p>
        </div>
    @else
        <div class="gallery-grid">
            @foreach ($photos as $photo)
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

        <div style="margin-top: 32px;">
            {{ $photos->links() }}
        </div>
    @endif

    <div style="margin-top: 32px;">
        <a href="{{ route('gallery.index') }}">← Ко всем альбомам</a>
    </div>
@endsection
