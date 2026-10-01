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
