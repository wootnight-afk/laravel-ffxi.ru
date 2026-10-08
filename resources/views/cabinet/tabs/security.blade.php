@php
    /** @var \App\Models\User $user */
    $emailVerified = $user->hasVerifiedEmail();
@endphp

@if (session('status'))
    <div class="form-status" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

<h2 class="section-title" style="margin-top: 0;">Безопасность</h2>

{{-- ============ Email ============ --}}
<div style="padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 24px; background: var(--bg-card);">
    <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 12px;">Email</h3>

    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
        Текущий: <strong>{{ $user->email }}</strong>
        @if ($emailVerified)
            <span style="color: #16a34a; margin-left: 6px;">✓ подтверждён</span>
        @else
            <span style="color: #dc2626; margin-left: 6px;">⚠ не подтверждён</span>
        @endif
    </div>

    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
        После изменения email вам потребуется подтвердить новый адрес.
        До подтверждения действия, требующие записи, будут недоступны.
        Ник изменить нельзя.
    </p>

    <form method="POST" action="{{ route('cabinet.security.email') }}">
        @csrf

        <div class="form-group">
            <label for="email">Новый email</label>
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
            <label for="email_current_password">Текущий пароль</label>
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

        <button type="submit" class="btn btn-primary">Изменить email</button>
    </form>
</div>

{{-- ============ Password ============ --}}
<div style="padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card);">
    <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 12px;">Пароль</h3>

    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
        Для смены пароля введите текущий пароль и новый дважды.
    </p>

    <form method="POST" action="{{ route('cabinet.security.password') }}">
        @csrf

        <div class="form-group">
            <label for="password_current_password">Текущий пароль</label>
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
            <label for="password">Новый пароль</label>
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
            <label for="password_confirmation">Повторите новый пароль</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                required
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn btn-primary">Изменить пароль</button>
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
    <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 12px;">Двухфакторная аутентификация (MFA)</h3>

    @error('mfa') <div class="form-error" style="margin-bottom: 12px;">{{ $message }}</div> @enderror

    @if (is_array($mfaRecoveryCodes) && count($mfaRecoveryCodes) > 0)
        {{-- Recovery codes — shown once, right after setup or regeneration. --}}
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
            MFA включена. Сохраните резервные коды в надёжном месте:
            каждый код можно использовать один раз, если вы потеряете доступ к приложению.
        </p>

        <div style="padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 12px; font-family: monospace; font-size: 13px;">
            @foreach ($mfaRecoveryCodes as $code)
                <div>{{ $code }}</div>
            @endforeach
        </div>

        <p style="font-size: 13px; color: #dc2626; margin-bottom: 12px;">
            Сохраните коды. Они больше не будут показаны.
        </p>

        <a href="{{ route('cabinet.tab', ['tab' => 'security']) }}" class="btn btn-primary">Я сохранил</a>
    @elseif (is_string($mfaSetupSecret) && $mfaSetupSecret !== '')
        {{-- Setup in progress. --}}
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
            Отсканируйте QR-код в Google Authenticator, Authy или 1Password,
            затем введите полученный код.
        </p>

        <div style="margin-bottom: 12px;">
            <img
                src="{{ \Filament\Auth\MultiFactor\App\AppAuthentication::make()->generateQrCodeDataUri($mfaSetupSecret) }}"
                alt="QR-код для настройки MFA"
                width="200"
                height="200"
            >
        </div>

        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">
            Не получается отсканировать? Введите ключ вручную:
        </p>
        <div style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 16px; font-family: monospace; font-size: 13px; word-break: break-all;">
            {{ $mfaSetupSecret }}
        </div>

        <form method="POST" action="{{ route('cabinet.security.mfa.confirm') }}" style="margin-bottom: 12px;">
            @csrf

            <div class="form-group">
                <label for="mfa_code">Код из приложения</label>
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

            <button type="submit" class="btn btn-primary">Подтвердить и включить</button>
        </form>

        <form method="POST" action="{{ route('cabinet.security.mfa.cancel') }}">
            @csrf
            <button type="submit" class="btn">Отмена</button>
        </form>
    @elseif ($mfaEnabled)
        {{-- Enabled. --}}
        <p style="font-size: 13px; color: #16a34a; margin-bottom: 6px;">✓ MFA настроена</p>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
            Осталось резервных кодов: <strong>{{ $mfaRecoveryCodesLeft }}</strong>
        </p>

        <form method="POST" action="{{ route('cabinet.security.mfa.regenerate-codes') }}" style="margin-bottom: 16px;">
            @csrf

            <div class="form-group">
                <label for="mfa_regen_password">Текущий пароль</label>
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

            <button type="submit" class="btn">Сгенерировать новые коды</button>
        </form>

        <form method="POST" action="{{ route('cabinet.security.mfa.disable') }}">
            @csrf

            <div class="form-group">
                <label for="mfa_disable_password">Текущий пароль</label>
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
                onclick="return confirm('Отключить MFA? Аккаунт станет менее защищённым.');"
            >
                Отключить MFA
            </button>
        </form>
    @else
        {{-- Not configured. --}}
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
            MFA не настроена. Подключите приложение-аутентификатор для дополнительной защиты аккаунта.
        </p>

        <form method="POST" action="{{ route('cabinet.security.mfa.setup') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Настроить MFA</button>
        </form>
    @endif
</div>
