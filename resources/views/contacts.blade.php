@extends('layouts.app')

@section('title', $metaTitle)
@section('metaDescription', $metaDescription ?? '')

@section('content')
    <h1 class="page-title">{{ $h1 }}</h1>
    <div class="content-text">
        @if ($bodyHtml !== '')
            {!! $bodyHtml !!}
        @else
            <p>{{ __('site.contacts.placeholder') }}</p>
            <p><a href="{{ route('home') }}">{{ __('site.contacts.back_home') }}</a></p>
        @endif
    </div>
@endsection
