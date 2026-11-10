@extends('errors.layout')

@section('code', '404')
@section('title', __('site.errors.404.title'))
@section('message', __('site.errors.404.message'))

@section('actions')
    <a href="{{ route('home') }}" class="btn btn-primary">{{ __('site.errors.home') }}</a>
    <button type="button" class="btn" onclick="history.back()">{{ __('site.errors.back') }}</button>
@endsection
