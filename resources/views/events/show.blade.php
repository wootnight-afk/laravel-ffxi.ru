@extends('layouts.app')

@section('title', $event->title)

@section('content')
    <article class="news-item">
        <p class="news-meta">
            {{ $event->type?->icon }} {{ $event->type?->title ?? __('site.events.fallback_type') }}
            · @include('events._time', ['event' => $event])
            @if ($event->duration_minutes)
                · {{ __('site.events.duration', ['count' => $event->duration_minutes]) }}
            @endif
        </p>
        <h1 class="page-title">{{ $event->title }}</h1>
        <p class="news-meta">
            {{ __('site.events.organizer') }} {{ $event->user?->name ?? __('site.common.unknown') }}
            · {{ $event->location ?: __('site.events.location_missing') }}
            · {{ trans_choice('site.events.participants_count', $event->joined_count) }}{{ $event->max_participants === null ? ' / ∞' : '/'.$event->max_participants }}
        </p>

        @if ($event->registration_close)
            <p class="news-meta">
                {{ __('site.events.registration_until') }} @include('events._time', ['event' => $event, 'value' => $event->registration_close])
            </p>
        @endif

        <div class="content-text">{!! $descriptionHtml !!}</div>

        <section style="margin-top: 28px;">
            <h2 class="section-title">{{ __('site.events.participants_title') }}</h2>
            @include('events._participant-list', [
                'participants' => $event->participants,
                'total' => $event->joined_count,
                'isGuest' => auth()->guest(),
            ])
        </section>

        @include('events._comments', ['event' => $event, 'comments' => $comments])

        @auth
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px;">
                @can('update', $event)
                    <a class="btn" href="{{ route('events.edit', $event) }}">{{ __('site.events.edit') }}</a>
                @endcan
                @can('cancel', $event)
                    <form method="POST" action="{{ route('events.cancel', $event) }}">
                        @csrf
                        <button class="btn" type="submit">{{ __('site.events.cancel') }}</button>
                    </form>
                @endcan
                @livewire('event-signup', ['event' => $event], key('event-signup-'.$event->id))
            </div>
        @else
            <p style="margin-top: 24px;">{!! __('site.events.login_to_join', ['login' => '<a href="'.route('login').'">'.e(__('site.events.login_link')).'</a>']) !!}</p>
        @endauth

        <p style="margin-top: 24px;"><a href="{{ route('events.index') }}">{{ __('site.events.back') }}</a></p>
    </article>
@endsection
