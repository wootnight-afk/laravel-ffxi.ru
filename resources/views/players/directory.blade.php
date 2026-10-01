@extends('layouts.app')

@section('title', 'Каталог игроков')

@section('content')
    <h1 class="page-title">Игроки</h1>

    <form method="GET" action="{{ route('players.directory') }}" style="margin-bottom: 24px;">
        <div class="form-group" style="display: flex; gap: 8px; max-width: 500px;">
            <input
                type="text"
                name="q"
                class="form-control"
                value="{{ $search }}"
                placeholder="Поиск по нику…"
                maxlength="24"
            >
            <button type="submit" class="btn btn-primary">Найти</button>
        </div>
    </form>

    @if ($users->isEmpty())
        <div class="content-text">
            <p>Игроков не найдено.</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            @foreach ($users as $user)
                <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; background: var(--bg-card);">
                    <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                        <x-avatar :user="$user" :size="64" />
                        <div style="min-width: 0;">
                            <div style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">
                                @if ($user->isProfilePublic() || $user->id === auth()->id() || auth()->user()->isAdmin())
                                    <a href="{{ route('players.show', $user->name) }}">{{ $user->name }}</a>
                                @else
                                    {{ $user->name }}
                                @endif
                            </div>
                            <div style="font-size: 12px; color: var(--text-muted);">
                                {{ $user->race ?? '—' }}
                                @if ($user->main_job)
                                    · {{ $user->main_job }}
                                @endif
                            </div>
                            @if ($user->rank)
                                <div style="margin-top: 4px;">
                                    <x-user-rank :user="$user" :compact="true" />
                                </div>
                            @endif
                        </div>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; gap: 8px 16px; font-size: 12px; color: var(--text-muted);">
                        <span>Новостей: <strong>{{ $user->news_count }}</strong></span>
                        <span>Комментариев: <strong>{{ $user->comments_count }}</strong></span>
                        <span>Регистрация: <strong>{{ $user->created_at->format('d.m.Y') }}</strong></span>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top: 32px;">
            {{ $users->links() }}
        </div>
    @endif
@endsection
