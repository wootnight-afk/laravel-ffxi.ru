@extends('layouts.app')

@section('title', 'Дашборд сообщества')

@section('content')
    <h1 class="page-title">Дашборд сообщества</h1>

    <div class="content-text">
        <p>Привет, <strong>{{ auth()->user()->name }}</strong>! Сейчас онлайн: <strong>{{ $onlineCount }}</strong> {{ trans_choice('игрок|игрока|игроков', $onlineCount) }}.</p>
        <p style="margin-top: 24px;">
            <a href="{{ route('players.directory') }}">Каталог игроков →</a>
        </p>
    </div>

    <div style="margin-top: 28px;">
        @livewire('events-board')
    </div>

    {{-- TODO(D6): migrate to DashboardWidget. --}}
    @include('players._activity-feed')
@endsection
