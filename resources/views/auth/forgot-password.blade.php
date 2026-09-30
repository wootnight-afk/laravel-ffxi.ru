@extends('layouts.app')

@section('title', 'Восстановление пароля')

@section('content')
    <h1 class="page-title">Восстановление пароля</h1>

    @if (session('status'))
        <div class="form-status">{{ session('status') }}</div>
    @endif

    <div class="content-text" style="max-width: 600px; margin-bottom: 16px;">
        <p>Укажите email, на который зарегистрирован аккаунт. Мы отправим ссылку для сброса пароля.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email</label>
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

        <button type="submit" class="btn btn-primary">Отправить ссылку</button>

        <div style="margin-top: 16px; font-size: 13px;">
            <a href="{{ route('login') }}">← Назад ко входу</a>
        </div>
    </form>
@endsection
