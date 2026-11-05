@extends('layouts.app')

@section('title', 'Создать событие')

@section('content')
    <h1 class="page-title">Создать событие</h1>
    @include('events._form', [
        'event' => $event,
        'types' => $types,
        'timezoneOptions' => $timezoneOptions,
        'defaultTimezone' => $defaultTimezone,
        'action' => route('events.store'),
        'method' => 'POST',
    ])
@endsection
