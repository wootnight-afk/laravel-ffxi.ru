@extends('layouts.app')

@section('title', __('site.auth.verify.title'))

@section('content')
    <h1 class="page-title">{{ __('site.auth.verify.heading') }}</h1>

    @if (session('status'))
        <div class="form-status">{{ session('status') }}</div>
    @endif

    <div class="content-text" style="max-width: 600px;">
        <p>{!! __('site.auth.verify.sent', ['email' => '<strong>'.e(auth()->user()->email).'</strong>']) !!}</p>
        <p>{{ __('site.auth.verify.explain') }}</p>

        <form method="POST" action="{{ route('verification.resend') }}" style="margin-top: 24px;">
            @csrf
            <button type="submit" class="btn">{{ __('site.auth.verify.resend') }}</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top: 24px;">
            @csrf
            <button type="submit" class="btn" style="background: none; border: none; padding: 0; color: var(--text-muted); text-decoration: underline; cursor: pointer; font-size: 13px;">
                {{ __('site.auth.verify.logout') }}
            </button>
        </form>
    </div>
@endsection
