@extends('layouts.app')

@section('title', $event->title)

@section('content')
    <article class="news-item">
        <p class="news-meta">
            {{ $event->type?->icon }} {{ $event->type?->title ?? 'Событие' }}
            · {{ $event->starts_at->copy()->setTimezone($timezone)->format('d.m.Y H:i') }}
            @if ($event->duration_minutes)
                · {{ $event->duration_minutes }} мин.
            @endif
        </p>
        <h1 class="page-title">{{ $event->title }}</h1>
        <p class="news-meta">
            Организатор: {{ $event->user?->name ?? 'неизвестен' }}
            · {{ $event->location ?: 'Место не указано' }}
            · {{ $event->max_participants === null ? $event->joined_count.' участников / ∞' : $event->joined_count.'/'.$event->max_participants }}
        </p>

        @if ($event->registration_close)
            <p class="news-meta">
                Регистрация до {{ $event->registration_close->copy()->setTimezone($timezone)->format('d.m.Y H:i') }}
            </p>
        @endif

        <div class="content-text">{!! $descriptionHtml !!}</div>

        <section style="margin-top: 28px;">
            <h2 class="section-title">Участники</h2>
            @include('events._participant-list', [
                'participants' => $event->participants,
                'total' => $event->joined_count,
                'isGuest' => auth()->guest(),
            ])
        </section>

        @auth
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px;">
                @can('update', $event)
                    <a class="btn" href="{{ route('events.edit', $event) }}">Изменить событие</a>
                @endcan
                @can('cancel', $event)
                    <form method="POST" action="{{ route('events.cancel', $event) }}">
                        @csrf
                        <button class="btn" type="submit">Отменить событие</button>
                    </form>
                @endcan
                @livewire('event-signup', ['event' => $event], key('event-signup-'.$event->id))
            </div>
        @else
            <p style="margin-top: 24px;"><a href="{{ route('login') }}">Войдите</a>, чтобы записаться на событие.</p>
        @endauth

        <p style="margin-top: 24px;"><a href="{{ route('events.index') }}">← Ко всем событиям</a></p>
    </article>
@endsection
