<div>
    <p class="news-meta" aria-live="polite">
        {{ trans_choice('site.events.participants_count', $joinedCount) }}
        @if ($event->max_participants !== null)
            / {{ $event->max_participants }}
        @else
            / ∞
        @endif
    </p>

    @if ($participation?->status === \App\Enums\EventParticipantStatus::Joined)
        @if ($canLeave)
            <button class="btn" type="button" wire:click="leave" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="leave">{{ __('site.events.signup.joined') }}</span>
                <span wire:loading wire:target="leave">{{ __('site.events.signup.processing') }}</span>
            </button>
        @endif
    @elseif ($canJoin)
        @if ($joinClosed || $eventFull)
            <button class="btn" type="button" disabled>
                {{ $eventFull ? __('site.events.signup.full') : __('site.events.signup.closed') }}
            </button>
        @else
            <button class="btn btn-primary" type="button" wire:click="join" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="join">{{ __('site.events.signup.join') }}</span>
                <span wire:loading wire:target="join">{{ __('site.events.signup.processing') }}</span>
            </button>
        @endif
    @elseif (auth()->guest())
        <a href="{{ route('login') }}">{{ __('site.events.signup.login') }}</a>
    @endif

    @error('event')
        <p role="alert" style="color: #dc2626;">{{ $message }}</p>
    @enderror
</div>
