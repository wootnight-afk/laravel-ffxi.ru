@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title">{{ $title }}</h1>

    @if ($description)
        <div class="content-text" style="margin-bottom: 16px;">
            {{ $description }}
        </div>
    @endif

    @if ($photos->isEmpty())
        <div class="content-text">
            <p>{{ __('site.gallery.album_empty_full') }}</p>
        </div>
    @else
        <div class="gallery-grid" data-details-label="{{ __('site.gallery.details') }}">
            @foreach ($photos as $photo)
                @php
                    $isEnglish = app()->getLocale() === \App\Enums\Locale::En->value;
                    $photoCaption = $isEnglish && filled($photo->caption_en)
                        ? $photo->caption_en
                        : $photo->caption;
                    $photoTitle = $isEnglish && filled($photo->title_en)
                        ? $photo->title_en
                        : $photo->title;
                    $photoSummary = $isEnglish && filled($photo->summary_en)
                        ? $photo->summary_en
                        : $photo->summary;
                @endphp
                <div class="gallery-item">
                    <a
                        href="{{ $photo->urlOriginal() }}"
                        data-pswp-width="{{ $photo->width ?: 1280 }}"
                        data-pswp-height="{{ $photo->height ?: 960 }}"
                        data-photo-id="{{ $photo->id }}"
                        @if ($photo->show_title_in_lightbox && filled($photoTitle))
                            data-pswp-title="{{ $photoTitle }}"
                        @endif
                        @if ($photo->show_summary_in_lightbox && filled($photoSummary))
                            data-pswp-summary="{{ $photoSummary }}"
                        @endif
                        data-photo-url="{{ route($routePrefix.'.photo', [...$routeParams, 'album' => $album->slug, 'photo' => $photo->id]) }}"
                    >
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

        <div style="margin-top: 32px;">
            {{ $photos->links() }}
        </div>
    @endif

    <div style="margin-top: 32px;">
        <a href="{{ route($routePrefix.'.index', $routeParams) }}">{{ __('site.gallery.back_to_albums') }}</a>
    </div>
@endsection
