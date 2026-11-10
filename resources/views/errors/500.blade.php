@extends('errors.layout')

@section('code', '500')
@section('title', __('site.errors.500.title'))
@section('message', __('site.errors.500.message'))

@section('actions')
    <a href="{{ route('home') }}" class="btn btn-primary">{{ __('site.errors.home') }}</a>
    <button type="button" class="btn" onclick="window.location.reload()">{{ __('site.errors.reload') }}</button>
@endsection
