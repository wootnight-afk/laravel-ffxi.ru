@extends('layouts.app')

@section('title', __('site.events.edit_title'))

@section('content')
    <h1 class="page-title">{{ __('site.events.edit_title') }}</h1>
    @include('events._form', [
        'event' => $event,
        'types' => $types,
        'timezoneOptions' => $timezoneOptions,
        'defaultTimezone' => $defaultTimezone,
        'action' => route('events.update', $event),
        'method' => 'PUT',
    ])
@endsection
