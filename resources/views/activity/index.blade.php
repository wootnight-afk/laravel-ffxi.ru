@extends('layouts.app')

@section('title', __('site.activity.title'))

@section('content')
    <h1 class="page-title">{{ __('site.activity.heading') }}</h1>

    @if ($groups === [])
        <div class="content-text"><p>{{ __('site.activity.empty') }}</p></div>
    @else
        <div>
            @foreach ($groups as $group)
                @include('activity._item', ['group' => $group, 'viewer' => $viewer])
            @endforeach
        </div>
        <div style="margin-top: 24px;">{{ $activities->links() }}</div>
    @endif
@endsection
