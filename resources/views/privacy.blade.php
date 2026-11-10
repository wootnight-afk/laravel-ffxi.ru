@extends('layouts.app')

@section('title', __('site.privacy.title'))

@section('content')
    <h1 class="page-title">{{ __('site.privacy.heading') }}</h1>

    <div class="content-text">
        <p><strong>{{ __('site.privacy.in_progress') }}</strong></p>
        <p>{{ __('site.privacy.in_progress_hint', ['email' => 'linkshell@yandex.ru']) }}</p>
    </div>
@endsection
