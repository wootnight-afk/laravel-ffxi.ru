@extends('layouts.app')

@section('title', __('site.auth.register.title'))

@section('content')
    <h1 class="page-title">{{ __('site.auth.register.title') }}</h1>

    <form
        method="POST"
        action="{{ route('register.store') }}"
        data-nickname-check-url="{{ route('api.nickname.check') }}"
    >
        @csrf

        <div class="form-group">
            <label for="name">{{ __('site.auth.register.name') }}</label>
            <input
                type="text"
                id="name"
                name="name"
                class="form-control"
                value="{{ old('name') }}"
                required
                autocomplete="username"
                minlength="3"
                maxlength="24"
                data-nickname-check-input
            >
            @error('name')
                <div class="form-error">{{ $message }}</div>
            @enderror
            <div data-nickname-status="checking" hidden style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                {{ __('site.auth.register.checking') }}
            </div>
            <div data-nickname-status="available" hidden style="font-size: 13px; color: #15803d; margin-top: 4px;">
                {{ __('site.auth.register.available') }}
            </div>
            <div data-nickname-status="taken" hidden style="font-size: 13px; color: #b45309; margin-top: 4px;">
                {{ __('site.auth.register.taken') }}
                <div data-nickname-suggestions style="margin-top: 6px; display: flex; gap: 8px; flex-wrap: wrap;"></div>
            </div>
            <div data-nickname-status="invalid" hidden style="font-size: 13px; color: #dc2626; margin-top: 4px;">
                {{ __('site.auth.register.invalid') }}
            </div>
        </div>

        <div class="form-group">
            <label for="email">{{ __('site.common.email') }}</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                value="{{ old('email') }}"
                required
                autocomplete="email"
            >
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">{{ __('site.auth.register.password') }}</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                required
                autocomplete="new-password"
                minlength="8"
            >
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">{{ __('site.auth.register.password_confirmation') }}</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                required
                autocomplete="new-password"
            >
        </div>

        <div class="form-group">
            <label for="phone">{{ __('site.auth.register.phone') }}</label>
            <input
                type="text"
                id="phone"
                name="phone"
                class="form-control"
                value="{{ old('phone') }}"
                maxlength="32"
            >
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                {{ __('site.auth.register.phone_hint') }}
            </div>
            @error('phone')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="max-width: 600px;">
            <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.5;">
                <input type="checkbox" name="pd_consent" value="1" {{ old('pd_consent') ? 'checked' : '' }} required style="margin-top: 3px;">
                <span>
                    {!! __('site.auth.register.pd_consent', [
                        'policy' => '<a href="'.route('privacy').'" target="_blank">'.e(__('site.auth.register.pd_policy')).'</a>',
                    ]) !!}
                </span>
            </label>
            @error('pd_consent')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="max-width: 600px;">
            <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.5;">
                <input type="checkbox" name="marketing_consent" value="1" {{ old('marketing_consent') ? 'checked' : '' }} style="margin-top: 3px;">
                <span>
                    {{ __('site.auth.register.marketing_consent') }}
                </span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary" data-register-submit>
            {{ __('site.auth.register.submit') }}
        </button>

        <div style="margin-top: 16px; font-size: 13px;">
            {{ __('site.auth.register.have_account') }} <a href="{{ route('login') }}">{{ __('site.common.login') }}</a>
        </div>
    </form>
@endsection
