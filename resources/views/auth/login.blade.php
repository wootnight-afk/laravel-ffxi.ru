@extends('layouts.app')

@section('title', __('site.auth.login.title'))

@section('content')
    <h1 class="page-title">{{ __('site.auth.login.title') }}</h1>

    @if (session('status'))
        <div class="form-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
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

        <div class="form-group">
            <label for="password">{{ __('site.auth.login.password') }}</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                required
            >
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                <input type="checkbox" name="remember" value="1">
                {{ __('site.auth.login.remember') }}
            </label>
        </div>

        <button type="submit" class="btn btn-primary">{{ __('site.auth.login.submit') }}</button>

        <div style="margin-top: 16px; font-size: 13px;">
            <a href="{{ route('password.request') }}">{{ __('site.auth.login.forgot') }}</a>
            <p style="color: var(--text-muted); margin-top: 4px;">
                {{ __('site.auth.login.forgot_hint') }}
            </p>
        </div>

        @if (Route::has('register'))
            <div style="margin-top: 16px; font-size: 13px;">
                {{ __('site.auth.login.no_account') }} <a href="{{ route('register') }}">{{ __('site.auth.login.create_account') }}</a>
            </div>
        @endif
    </form>
@endsection
