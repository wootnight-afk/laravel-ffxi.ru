@extends('layouts.app')

@section('title', 'Изменить событие')

@section('content')
    <h1 class="page-title">Изменить событие</h1>
    @include('events._form', [
        'event' => $event,
        'types' => $types,
        'timezone' => $timezone,
        'action' => route('events.update', $event),
        'method' => 'PUT',
    ])
@endsection
