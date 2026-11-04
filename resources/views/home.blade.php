@extends('layouts.app')

@section('title', $home['metaTitle'])

@section('content')

    <div class="hero hero--single">
        <div class="logo-wrap{{ $home['heroLogoIsCustom'] ? ' logo-wrap--custom' : '' }}">
            <img src="{{ $home['heroLogoUrl'] }}" alt="{{ $home['h1'] }}">
        </div>
    </div>

    <h1 class="page-title sr-only">{{ $home['h1'] }}</h1>

    <img class="home-image" src="{{ $home['mainImageUrl'] }}" alt="{{ $home['h1'] }}" width="{{ $home['mainImageWidth'] }}" height="{{ $home['mainImageHeight'] }}">

    @if ($home['bodyHtml'] !== '')
        <div class="content-text">
            {!! $home['bodyHtml'] !!}
        </div>
    @endif

@endsection
