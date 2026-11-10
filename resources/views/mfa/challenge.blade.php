@extends('layouts.app')

@section('title', __('site.mfa.title'))

@section('content')
    <h1 class="page-title">{{ __('site.mfa.title') }}</h1>

    <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">
        {{ __('site.mfa.intro') }}
    </p>

    <form method="POST" action="{{ route('mfa.challenge.verify') }}" x-data="{ recovery: false }">
        @csrf

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                <input type="checkbox" name="use_recovery_code" value="1" x-model="recovery">
                {{ __('site.mfa.use_recovery') }}
            </label>
        </div>

        <div class="form-group" x-show="!recovery">
            <label for="mfa_code">{{ __('site.mfa.code') }}</label>
            <input
                type="text"
                id="mfa_code"
                name="code"
                class="form-control"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="\d{6}"
                maxlength="6"
                autofocus
            >
            @error('code') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group" x-show="recovery" x-cloak>
            <label for="mfa_recovery_code">{{ __('site.mfa.recovery_code') }}</label>
            <input
                type="text"
                id="mfa_recovery_code"
                name="recovery_code"
                class="form-control"
                autocomplete="off"
            >
            @error('recovery_code') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary">{{ __('site.mfa.submit') }}</button>
    </form>
@endsection
