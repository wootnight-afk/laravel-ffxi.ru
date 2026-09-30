@extends('layouts.app')

@section('title', 'Сброс пароля')

@section('content')
    <h1 class="page-title">Сброс пароля</h1>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                value="{{ old('email', $email) }}"
                required
                autofocus
            >
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
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
                minlength="8"
            >
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">Новый пароль ещё раз</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                required
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn btn-primary">Сбросить пароль</button>
    </form>
@endsection
