@extends('layouts.app')

@section('title', $page['metaTitle'])
@section('metaDescription', $page['metaDescription'] ?? '')

@section('content')
    <h1 class="page-title">{{ $page['h1'] }}</h1>
    @if ($page['bodyHtml'] !== '')
        <div class="content-text">{!! $page['bodyHtml'] !!}</div>
    @endif
    @include('events._form', [
        'event' => $event,
        'types' => $types,
        'timezoneOptions' => $timezoneOptions,
        'defaultTimezone' => $defaultTimezone,
        'action' => route('events.store'),
        'method' => 'POST',
    ])
@endsection
