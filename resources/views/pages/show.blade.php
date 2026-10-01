@extends('layouts.app')

@section('title', $page->meta_title ?: $page->title)

@section('content')
    <h1 class="page-title">{{ $page->title }}</h1>

    <div class="content-text">
        {!! $page->body_html !!}
    </div>
@endsection
