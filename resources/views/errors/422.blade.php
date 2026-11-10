@extends('errors.layout')

@section('code', '422')
@section('title', __('site.errors.422.title'))
@section('message', __('site.errors.422.message'))

@section('actions')
    <button type="button" class="btn btn-primary" onclick="history.back()">{{ __('site.errors.back') }}</button>
    <a href="{{ route('home') }}" class="btn">{{ __('site.errors.home') }}</a>
@endsection
