@extends('layouts.app')

@section('title', $metaTitle ?: $title)

@section('content')
    <h1 class="page-title">{{ $title }}</h1>

    <div class="content-text">
        {!! $bodyHtml !!}
    </div>
@endsection
