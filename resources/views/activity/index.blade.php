@extends('layouts.app')

@section('title', 'Активность')

@section('content')
    <h1 class="page-title">Активность сообщества</h1>

    @if ($groups === [])
        <div class="content-text"><p>Пока активности нет.</p></div>
    @else
        <div>
            @foreach ($groups as $group)
                @include('activity._item', ['group' => $group, 'viewer' => $viewer])
            @endforeach
        </div>
        <div style="margin-top: 24px;">{{ $activities->links() }}</div>
    @endif
@endsection
