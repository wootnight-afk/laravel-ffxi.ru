@extends('layouts.app')

@section('title', 'Дашборд сообщества')

@section('content')
    <h1 class="page-title">Дашборд сообщества</h1>

    <div class="content-text">
        <p>Привет, <strong>{{ auth()->user()->name }}</strong>!</p>
    </div>

    <div class="dashboard-grid">
        @foreach ($widgets as $widget)
            @php($span = max(1, min(3, $widget->column_span)))
            <div class="dashboard-widget dashboard-widget--span-{{ $span }}" wire:key="dashboard-widget-{{ $widget->id }}">
                @if ($widget->type === 'community_chat')
                    @if (app(\App\Services\SettingsRepository::class)->bool('chat_enabled', true))
                        @livewire('chat-room', key('dashboard-chat-'.$widget->id))
                    @endif
                @elseif ($widget->type === 'events_board')
                    @livewire('events-board', key('dashboard-events-'.$widget->id))
                @elseif ($widget->type === 'activity_feed')
                    @include('players._activity-feed', ['activityGroups' => $activityGroups, 'viewer' => $viewer])
                @elseif ($widget->type === 'online_users')
                    @livewire('online-users', key('dashboard-online-users-'.$widget->id))
                @endif
            </div>
        @endforeach
    </div>
@endsection
