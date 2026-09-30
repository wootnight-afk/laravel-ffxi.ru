@extends('layouts.app')

@section('title', 'Подтверждение email')

@section('content')
    <h1 class="page-title">Подтвердите ваш email</h1>

    @if (session('status'))
        <div class="form-status">{{ session('status') }}</div>
    @endif

    <div class="content-text" style="max-width: 600px;">
        <p>Мы отправили письмо со ссылкой для подтверждения на <strong>{{ auth()->user()->email }}</strong>.</p>
        <p>Перейдите по ссылке в письме — это активирует возможность комментировать, публиковать новости и записываться на события.</p>

        <form method="POST" action="{{ route('verification.resend') }}" style="margin-top: 24px;">
            @csrf
            <button type="submit" class="btn">Отправить письмо ещё раз</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top: 24px;">
            @csrf
            <button type="submit" class="btn" style="background: none; border: none; padding: 0; color: var(--text-muted); text-decoration: underline; cursor: pointer; font-size: 13px;">
                Выйти из аккаунта
            </button>
        </form>
    </div>
@endsection
