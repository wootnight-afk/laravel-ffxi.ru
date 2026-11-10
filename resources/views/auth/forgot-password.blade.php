@extends('layouts.app')

@section('title', __('site.auth.forgot.title'))

@section('content')
    <h1 class="page-title">{{ __('site.auth.forgot.title') }}</h1>

    @if (session('status'))
        <div class="form-status">{{ session('status') }}</div>
    @endif

    <div class="content-text" style="max-width: 600px; margin-bottom: 16px;">
        <p>{{ __('site.auth.forgot.intro') }}</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-group">
            <label for="email">{{ __('site.common.email') }}</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                value="{{ old('email') }}"
                required
                autofocus
            >
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">{{ __('site.auth.forgot.submit') }}</button>

        <div style="margin-top: 16px; font-size: 13px;">
            <a href="{{ route('login') }}">{{ __('site.auth.forgot.back') }}</a>
        </div>
    </form>
@endsection
