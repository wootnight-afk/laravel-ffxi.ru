@extends('layouts.app')

@section('title', 'События')

@section('content')
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
        <h1 class="page-title">События</h1>
        @auth
            @can('create', \App\Models\Event::class)
                <a class="btn btn-primary" href="{{ route('events.create') }}">+ Создать событие</a>
            @endcan
        @endauth
    </div>

    <form method="GET" action="{{ route('events.index') }}" class="form-group" style="max-width: 360px;">
        <label for="event-type">Тип события</label>
        <select id="event-type" name="type" class="form-control" onchange="this.form.submit()">
            <option value="">Все типы</option>
            @foreach ($types as $type)
                <option value="{{ $type->key }}" @selected($selectedType === $type->key)>
                    {{ $type->title }}
                </option>
            @endforeach
        </select>
    </form>

    @if ($events->isEmpty())
        <div class="content-text">
            <p>Ближайших событий пока нет.</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 16px;">
            @foreach ($events as $event)
                @include('events._card', ['event' => $event, 'timezone' => $timezone])
            @endforeach
        </div>

        <div style="margin-top: 24px;">
            {{ $events->links() }}
        </div>
    @endif
@endsection
