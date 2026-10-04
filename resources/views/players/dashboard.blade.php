@extends('layouts.app')

@section('title', 'Дашборд сообщества')

@section('content')
    <h1 class="page-title">Дашборд сообщества</h1>

    <div class="content-text">
        <p>Привет, <strong>{{ auth()->user()->name }}</strong>!</p>
    </div>

    {{-- TODO(D6): migrate to DashboardWidget and configured spans. --}}
    @if (app(\App\Services\SettingsRepository::class)->bool('chat_enabled', true))
        @livewire('chat-room')
    @endif

    <div style="margin-top: 28px;">
        @livewire('events-board')
    </div>

    {{-- TODO(D6): migrate to DashboardWidget. --}}
    @include('players._activity-feed')

    {{-- TODO(D6): migrate to DashboardWidget. --}}
    @livewire('online-users')
@endsection
