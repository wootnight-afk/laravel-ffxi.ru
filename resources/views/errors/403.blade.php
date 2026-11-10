@extends('errors.layout')

@section('code', '403')
@section('title', __('site.errors.403.title'))
@section('message', __('site.errors.403.message'))

@section('actions')
    <a href="{{ route('home') }}" class="btn btn-primary">{{ __('site.errors.home') }}</a>
@endsection
