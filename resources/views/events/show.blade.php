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
                @if ($viewerParticipation?->status === \App\Enums\EventParticipantStatus::Joined)
                    <form method="POST" action="{{ route('events.leave', $event) }}">
                        @csrf
                        <button class="btn" type="submit">Выйти</button>
                    </form>
                @elseif (auth()->user()->can('join', $event) && $event->status === \App\Enums\EventStatus::Planned)
                    @php
                        $joinClosed = $event->starts_at->lessThanOrEqualTo(now())
                            || ($event->registration_close !== null && $event->registration_close->lessThanOrEqualTo(now()));
                        $eventFull = $event->max_participants !== null && $event->joined_count >= $event->max_participants;
                    @endphp
                    @if ($joinClosed || $eventFull)
                        <button class="btn" type="button" disabled>
                            {{ $eventFull ? 'Мест нет' : 'Регистрация закрыта' }}
                        </button>
                    @else
                        <form method="POST" action="{{ route('events.join', $event) }}">
                            @csrf
                            <button class="btn btn-primary" type="submit">Записаться</button>
                        </form>
                    @endif
                @endif
            </div>
        @else
            <p style="margin-top: 24px;"><a href="{{ route('login') }}">Войдите</a>, чтобы записаться на событие.</p>
        @endauth

        <p style="margin-top: 24px;"><a href="{{ route('events.index') }}">← Ко всем событиям</a></p>
    </article>
@endsection
