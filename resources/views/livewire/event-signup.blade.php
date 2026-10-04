<div>
    <p class="news-meta" aria-live="polite">
        {{ $joinedCount }} участников
        @if ($event->max_participants !== null)
            / {{ $event->max_participants }}
        @else
            / ∞
        @endif
    </p>

    @if ($participation?->status === \App\Enums\EventParticipantStatus::Joined)
        @if ($canLeave)
            <button class="btn" type="button" wire:click="leave" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="leave">Выйти</span>
                <span wire:loading wire:target="leave">Обработка…</span>
            </button>
        @endif
    @elseif ($canJoin)
        @if ($joinClosed || $eventFull)
            <button class="btn" type="button" disabled>
                {{ $eventFull ? 'Мест нет' : 'Регистрация закрыта' }}
            </button>
        @else
            <button class="btn btn-primary" type="button" wire:click="join" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="join">Записаться</span>
                <span wire:loading wire:target="join">Обработка…</span>
            </button>
        @endif
    @elseif (auth()->guest())
        <a href="{{ route('login') }}">Войдите, чтобы записаться</a>
    @endif

    @error('event')
        <p role="alert" style="color: #dc2626;">{{ $message }}</p>
    @enderror
</div>
