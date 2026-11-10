@extends('layouts.app')

@section('title', __('site.auth.closed.title'))

@section('content')
    <h1 class="page-title">{{ __('site.auth.closed.title') }}</h1>

    <div class="content-text" style="max-width: 600px;">
        <p>{{ __('site.auth.closed.message') }}</p>
        <p>{{ __('site.auth.closed.contact') }}</p>
        <p><a href="mailto:linkshell@yandex.ru">linkshell@yandex.ru</a></p>
        <p style="margin-top: 24px;">
            <a href="{{ route('home') }}">{{ __('site.auth.closed.back') }}</a>
        </p>
    </div>
@endsection
