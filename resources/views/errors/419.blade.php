@extends('errors.layout')

@section('code', '419')
@section('title', __('site.errors.419.title'))
@section('message', __('site.errors.419.message'))

@section('actions')
    <button type="button" class="btn btn-primary" onclick="window.location.reload()">{{ __('site.errors.reload') }}</button>
    <a href="{{ route('home') }}" class="btn">{{ __('site.errors.home') }}</a>
@endsection
