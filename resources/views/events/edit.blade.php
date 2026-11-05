@extends('layouts.app')

@section('title', 'Изменить событие')

@section('content')
    <h1 class="page-title">Изменить событие</h1>
    @include('events._form', [
        'event' => $event,
        'types' => $types,
        'timezoneOptions' => $timezoneOptions,
        'defaultTimezone' => $defaultTimezone,
        'action' => route('events.update', $event),
        'method' => 'PUT',
    ])
@endsection
