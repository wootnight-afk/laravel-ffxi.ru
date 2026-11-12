@extends('layouts.app')

@section('title', $caption ?: $title)

@php
    $commentAction = $album->isSite()
        ? route('gallery.photo.comments.store', ['album' => $album->slug, 'photo' => $photo->id])
        : route('gallery.player.comments.store', [...$routeParams, 'album' => $album->slug, 'photo' => $photo->id]);
@endphp

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h1 class="page-title" style="margin-bottom: 0;">{{ $title }}</h1>
        <a href="{{ route($routePrefix.'.album', [...$routeParams, 'album' => $album->slug]) }}">{{ __('site.gallery.back_to_album') }}</a>
    </div>

    {{-- Модальное окно с полноразмерным изображением --}}
    <div data-photo-zoom style="position: relative;">
        <div
            style="text-align: center; cursor: zoom-in; margin-bottom: 16px;"
            data-photo-zoom-open
        >
            <img
                src="{{ $photo->urlOriginal() }}"
                alt="{{ $caption ?: __('site.gallery.photo_alt') }}"
                style="max-width: 100%; height: auto; border-radius: 4px;"
            >
        </div>

        <div
            data-photo-zoom-overlay
            hidden
            style="position: fixed; inset: 0; background: rgba(0,0,0,0.92); z-index: 9999; display: flex; align-items: center; justify-content: center; cursor: zoom-out;"
        >
            <img
                src="{{ $photo->urlOriginal() }}"
                alt="{{ $caption ?: __('site.gallery.photo_alt') }}"
                style="max-width: 95vw; max-height: 95vh;"
            >
        </div>
    </div>

    @if ($caption)
        <p style="font-size: 14px; margin-bottom: 8px;">{{ $caption }}</p>
    @endif

    {{-- Дополнительное описание: только для этой страницы, краткое описание PhotoSwipe здесь не дублируется. --}}
    @if ($details)
        <div class="content-text" style="font-size: 14px; margin-bottom: 16px;">
            {!! nl2br(e($details)) !!}
        </div>
    @endif

    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 24px;">
        @if ($photo->taken_at)
            {{ __('site.gallery.taken') }} <x-naive-date :value="$photo->taken_at" />
        @endif
        @if ($photo->width && $photo->height)
            · {{ $photo->width }}×{{ $photo->height }}
        @endif
    </div>

    {{-- Навигация prev/next --}}
    <div style="display: flex; justify-content: space-between; margin-bottom: 32px;">
        <div>
            @if ($prevPhoto)
                <a href="{{ route($routePrefix.'.photo', [...$routeParams, 'album' => $album->slug, 'photo' => $prevPhoto->id]) }}">{{ __('site.gallery.previous') }}</a>
            @endif
        </div>
        <div>
            @if ($nextPhoto)
                <a href="{{ route($routePrefix.'.photo', [...$routeParams, 'album' => $album->slug, 'photo' => $nextPhoto->id]) }}">{{ __('site.gallery.next') }}</a>
            @endif
        </div>
    </div>

    <section style="margin-top: 48px;">
        <h2 class="section-title">{{ __('site.comments.title') }}</h2>

        <x-comment-thread :comments="$comments" />
        <x-comment-form :action="$commentAction" />
    </section>
@endsection
