@php
    /** @var \App\Models\User $user */
    $emailVerified = $user->hasVerifiedEmail();
@endphp

@if (session('status'))
    <div class="form-status" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

<h2 class="section-title" style="margin-top: 0;">{{ __('site.cabinet.security.title') }}</h2>

{{-- ============ Email ============ --}}
<div style="padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 24px; background: var(--bg-card);">
    <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 12px;">Email</h3>

    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
        {{ __('site.cabinet.security.current') }} <strong>{{ $user->email }}</strong>
        @if ($emailVerified)
            <span style="color: #16a34a; margin-left: 6px;">{{ __('site.cabinet.security.verified') }}</span>
        @else
            <span style="color: #dc2626; margin-left: 6px;">{{ __('site.cabinet.security.unverified') }}</span>
        @endif
    </div>

    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
        {{ __('site.cabinet.security.email_hint') }}
    </p>

    <form method="POST" action="{{ route('cabinet.security.email') }}">
        @csrf

        <div class="form-group">
            <label for="email">{{ __('site.cabinet.security.new_email') }}</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                value="{{ old('email') }}"
                maxlength="255"
                required
                autocomplete="email"
            >
            @error('email') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="email_current_password">{{ __('site.cabinet.security.current_password') }}</label>
            <input
                type="password"
                id="email_current_password"
                name="current_password"
                class="form-control"
                required
                autocomplete="current-password"
            >
            @error('current_password') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary">{{ __('site.cabinet.security.change_email') }}</button>
    </form>
</div>

{{-- ============ Password ============ --}}
<div style="padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card);">
    <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 12px;">{{ __('site.cabinet.security.password') }}</h3>

    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
        {{ __('site.cabinet.security.password_hint') }}
    </p>

    <form method="POST" action="{{ route('cabinet.security.password') }}">
        @csrf

        <div class="form-group">
            <label for="password_current_password">{{ __('site.cabinet.security.current_password') }}</label>
            <input
                type="password"
                id="password_current_password"
                name="current_password"
                class="form-control"
                required
                autocomplete="current-password"
            >
            @error('current_password') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="password">{{ __('site.cabinet.security.new_password') }}</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                required
                autocomplete="new-password"
            >
            @error('password') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">{{ __('site.cabinet.security.repeat_new_password') }}</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                required
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn btn-primary">{{ __('site.cabinet.security.change_password') }}</button>
    </form>
</div>

{{-- ============ MFA ============ --}}
@php
    $mfaEnabled = filled($user->getAppAuthenticationSecret());
    $mfaSetupSecret = session('mfa_setup_secret');
    $mfaRecoveryCodes = session('mfa_recovery_codes');
    $mfaRecoveryCodesLeft = count($user->getAppAuthenticationRecoveryCodes() ?? []);
@endphp

<div style="padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 24px; background: var(--bg-card);">
    <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 12px;">{{ __('site.cabinet.security.mfa_title') }}</h3>

    @error('mfa') <div class="form-error" style="margin-bottom: 12px;">{{ $message }}</div> @enderror

    @if (is_array($mfaRecoveryCodes) && count($mfaRecoveryCodes) > 0)
        {{-- Recovery codes — shown once, right after setup or regeneration. --}}
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
            {{ __('site.cabinet.security.mfa_codes_hint') }}
        </p>

        <div style="padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 12px; font-family: monospace; font-size: 13px;">
            @foreach ($mfaRecoveryCodes as $code)
                <div>{{ $code }}</div>
            @endforeach
        </div>

        <p style="font-size: 13px; color: #dc2626; margin-bottom: 12px;">
            {{ __('site.cabinet.security.mfa_codes_warning') }}
        </p>

        <a href="{{ route('cabinet.tab', ['tab' => 'security']) }}" class="btn btn-primary">{{ __('site.cabinet.security.mfa_saved') }}</a>
    @elseif (is_string($mfaSetupSecret) && $mfaSetupSecret !== '')
        {{-- Setup in progress. --}}
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
            {{ __('site.cabinet.security.mfa_scan') }}
        </p>

        <div style="margin-bottom: 12px;">
            <img
                src="{{ \Filament\Auth\MultiFactor\App\AppAuthentication::make()->generateQrCodeDataUri($mfaSetupSecret) }}"
                alt="{{ __('site.cabinet.security.mfa_qr_alt') }}"
                width="200"
                height="200"
            >
        </div>

        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">
            {{ __('site.cabinet.security.mfa_manual') }}
        </p>
        <div style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 16px; font-family: monospace; font-size: 13px; word-break: break-all;">
            {{ $mfaSetupSecret }}
        </div>

        <form method="POST" action="{{ route('cabinet.security.mfa.confirm') }}" style="margin-bottom: 12px;">
            @csrf

            <div class="form-group">
                <label for="mfa_code">{{ __('site.cabinet.security.mfa_code') }}</label>
                <input
                    type="text"
                    id="mfa_code"
                    name="code"
                    class="form-control"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="\d{6}"
                    maxlength="6"
                    required
                >
                @error('code') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary">{{ __('site.cabinet.security.mfa_confirm') }}</button>
        </form>

        <form method="POST" action="{{ route('cabinet.security.mfa.cancel') }}">
            @csrf
            <button type="submit" class="btn">{{ __('site.cabinet.security.mfa_cancel') }}</button>
        </form>
    @elseif ($mfaEnabled)
        {{-- Enabled. --}}
        <p style="font-size: 13px; color: #16a34a; margin-bottom: 6px;">{{ __('site.cabinet.security.mfa_enabled') }}</p>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
            {{ __('site.cabinet.security.mfa_codes_left') }} <strong>{{ $mfaRecoveryCodesLeft }}</strong>
        </p>

        <form method="POST" action="{{ route('cabinet.security.mfa.regenerate-codes') }}" style="margin-bottom: 16px;">
            @csrf

            <div class="form-group">
                <label for="mfa_regen_password">{{ __('site.cabinet.security.current_password') }}</label>
                <input
                    type="password"
                    id="mfa_regen_password"
                    name="password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                >
                @error('password') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn">{{ __('site.cabinet.security.mfa_regenerate') }}</button>
        </form>

        <form method="POST" action="{{ route('cabinet.security.mfa.disable') }}">
            @csrf

            <div class="form-group">
                <label for="mfa_disable_password">{{ __('site.cabinet.security.current_password') }}</label>
                <input
                    type="password"
                    id="mfa_disable_password"
                    name="password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                >
                @error('password') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <button
                type="submit"
                class="btn"
                style="background: #dc2626; color: #fff; border-color: #dc2626;"
                onclick="return confirm('{{ __('site.cabinet.security.mfa_disable_confirm') }}');"
            >
                {{ __('site.cabinet.security.mfa_disable') }}
            </button>
        </form>
    @else
        {{-- Not configured. --}}
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
            {{ __('site.cabinet.security.mfa_not_configured') }}
        </p>

        <form method="POST" action="{{ route('cabinet.security.mfa.setup') }}">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('site.cabinet.security.mfa_setup') }}</button>
        </form>
    @endif
</div>
