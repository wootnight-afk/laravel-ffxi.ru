@extends('layouts.app')

@section('title', 'Создать событие')

@section('content')
    <h1 class="page-title">Создать событие</h1>
    @include('events._form', [
        'event' => $event,
        'types' => $types,
        'timezone' => $timezone,
        'action' => route('events.store'),
        'method' => 'POST',
    ])
@endsection
