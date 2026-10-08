@extends('layouts.app')

@section('title', 'Подтверждение входа')

@section('content')
    <h1 class="page-title">Подтверждение входа</h1>

    <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">
        Введите код из приложения-аутентификатора, чтобы продолжить.
    </p>

    <form method="POST" action="{{ route('mfa.challenge.verify') }}" x-data="{ recovery: false }">
        @csrf

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                <input type="checkbox" name="use_recovery_code" value="1" x-model="recovery">
                Использовать резервный код
            </label>
        </div>

        <div class="form-group" x-show="!recovery">
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
                autofocus
            >
            @error('code') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group" x-show="recovery" x-cloak>
            <label for="mfa_recovery_code">Резервный код</label>
            <input
                type="text"
                id="mfa_recovery_code"
                name="recovery_code"
                class="form-control"
                autocomplete="off"
            >
            @error('recovery_code') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Подтвердить</button>
    </form>
@endsection
