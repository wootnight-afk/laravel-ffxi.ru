@extends('layouts.app')

@section('title', $page['metaTitle'])
@section('metaDescription', $page['metaDescription'] ?? '')

@section('content')
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
        <h1 class="page-title">{{ $page['h1'] }}</h1>
        @auth
            @can('create', \App\Models\Event::class)
                <a class="btn btn-primary" href="{{ route('events.create') }}">{{ __('site.events.create') }}</a>
            @endcan
        @endauth
    </div>

    @if ($page['bodyHtml'] !== '')
        <div class="content-text">{!! $page['bodyHtml'] !!}</div>
    @endif

    <form method="GET" action="{{ route('events.index') }}" class="form-group" style="max-width: 360px;">
        <label for="event-type">{{ __('site.events.type_filter') }}</label>
        <select id="event-type" name="type" class="form-control" onchange="this.form.submit()">
            <option value="">{{ __('site.events.all_types') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->key }}" @selected($selectedType === $type->key)>
                    {{ $type->title }}
                </option>
            @endforeach
        </select>
    </form>

    @if ($events->isEmpty())
        <div class="content-text">
            <p>{{ __('site.events.empty') }}</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 16px;">
            @foreach ($events as $event)
                @include('events._card', ['event' => $event])
            @endforeach
        </div>

        <div style="margin-top: 24px;">
            {{ $events->links() }}
        </div>
    @endif
@endsection
