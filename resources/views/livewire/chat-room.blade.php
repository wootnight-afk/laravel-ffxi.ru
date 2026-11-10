<section
    class="community-chat"
    wire:poll.{{ $this->pollingInterval }}s="pollMessages"
    x-data="{
        scrollToEnd() { this.$nextTick(() => { this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight }) },
        rememberPosition() { this.previousHeight = this.$refs.messages.scrollHeight },
        previousHeight: 0
    }"
    x-init="scrollToEnd()"
    x-on:chat-message-posted.window="scrollToEnd()"
    x-on:chat-scroll-bottom.window="scrollToEnd()"
    x-on:chat-history-loaded.window="$nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight - previousHeight })"
    x-on:chat-new-messages.window="if ($event.detail.scroll) scrollToEnd()"
>
    @php
        $viewerTimezone = app(\App\Services\TimezoneResolver::class)->resolve(auth()->user());
    @endphp

    <header class="community-chat__header">
        <h2 class="section-title">{{ __('site.chat.title') }}</h2>
    </header>

    @if ($isMuted)
        <p class="community-chat__notice" role="status">
            {{ __('site.chat.muted') }}
            @if (auth()->user()->chat_banned_permanently)
                {{ __('site.chat.muted_forever') }}
            @else
                {{ __('site.chat.muted_until') }} <x-datetime :value="auth()->user()->chat_banned_until" />.
            @endif
        </p>
    @endif

    <div
        class="community-chat__messages"
        x-ref="messages"
        role="log"
        aria-live="polite"
        aria-relevant="additions text"
        x-on:scroll.debounce.100ms="$wire.set('isAtBottom', ($refs.messages.scrollHeight - $refs.messages.scrollTop - $refs.messages.clientHeight) < 80, false)"
        x-on:scroll.passive="if (($refs.messages.scrollHeight - $refs.messages.scrollTop - $refs.messages.clientHeight) > 80) { rememberPosition() }"
    >
        @if ($messages->isEmpty())
            <p class="news-meta">{{ __('site.chat.empty') }}</p>
        @else
            @php
                $previousDate = null;
            @endphp
            @foreach ($messages as $message)
                @php
                    $messageDate = $message->created_at->copy()->setTimezone($viewerTimezone)->toDateString();
                    $dateLabel = \App\Support\DateFormat::localDayLabel(
                        $message->created_at,
                        $viewerTimezone,
                        app()->getLocale(),
                    );
                @endphp

                @if ($messageDate !== $previousDate)
                    <div class="community-chat__date-divider"><span>{{ $dateLabel }}</span></div>
                    @php
                        $previousDate = $messageDate;
                    @endphp
                @endif

                <article class="community-chat__message" wire:key="chat-message-{{ $message->id }}">
                    <div class="community-chat__message-content">
                        @if ($message->user)
                            <x-user-identity :user="$message->user" context="chat" />
                        @else
                            <span class="user-identity user-deleted">{{ __('site.common.deleted_account') }}</span>
                        @endif

                        @if ($message->is_deleted)
                            <span class="community-chat__deleted">{{ __('site.chat.deleted') }}</span>
                        @else
                            <span class="community-chat__body">
                                @foreach (app(\App\Services\ChatMentionParser::class)->segments($message->body, $mentionUsers, $message->user) as $segment)
                                    @if ($segment['type'] === 'mention')
                                        @php
                                            $mentioned = $segment['user'];
                                            $mentionLinkable = $mentioned->isProfilePublic()
                                                && app(\App\Services\ActivitySubjectResolver::class)->actorLinkable($mentioned, $viewer);
                                        @endphp
                                        @if ($mentionLinkable)
                                            <a class="community-chat__mention" href="{{ route('players.show', $mentioned->name) }}">{{ $segment['text'] }}</a>
                                        @else
                                            <span class="community-chat__mention">{{ $segment['text'] }}</span>
                                        @endif
                                    @else
                                        {{ $segment['text'] }}
                                    @endif
                                @endforeach
                            </span>
                        @endif

                        <x-datetime :value="$message->created_at" format="time" class="news-meta" />
                    </div>

                    @if ($canModerate)
                        <div class="community-chat__moderation">
                            @if (! $message->is_deleted)
                                <form wire:submit="deleteMessage({{ $message->id }})">
                                    <input
                                        class="form-control community-chat__reason"
                                        type="text"
                                        maxlength="100"
                                        wire:model="deleteReason"
                                        aria-label="{{ __('site.chat.delete_reason_aria') }}"
                                        placeholder="{{ __('site.chat.delete_reason_placeholder') }}"
                                    >
                                    <button class="btn" type="submit" wire:loading.attr="disabled">{{ __('site.chat.delete') }}</button>
                                </form>
                            @endif

                            @if ($message->user)
                                @if ($message->user->isChatBanned())
                                    <button class="btn" type="button" wire:click="unbanUser({{ $message->user->id }})" wire:loading.attr="disabled">
                                        {{ __('site.chat.unban') }}
                                    </button>
                                @else
                                    <form wire:submit="banUser({{ $message->user->id }})">
                                        <label class="sr-only" for="chat-ban-duration-{{ $message->id }}">{{ __('site.chat.ban_duration') }}</label>
                                        <select id="chat-ban-duration-{{ $message->id }}" class="form-control" wire:model="banDuration">
                                            <option value="1h">{{ __('site.chat.ban_1h') }}</option>
                                            <option value="1d">{{ __('site.chat.ban_1d') }}</option>
                                            <option value="7d">{{ __('site.chat.ban_7d') }}</option>
                                            <option value="permanent">{{ __('site.chat.ban_permanent') }}</option>
                                        </select>
                                        <button class="btn" type="submit" wire:loading.attr="disabled">{{ __('site.chat.ban') }}</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        @endif
    </div>

    @if (count($messageIds) >= 50)
        <button class="btn community-chat__load-more" type="button" wire:click="loadMore" wire:loading.attr="disabled" x-on:click="rememberPosition()">
            {{ __('site.chat.load_more') }}
        </button>
    @endif

    <button
        class="community-chat__new-pill"
        type="button"
        x-show="$wire.newMessageCount > 0"
        x-on:click="$wire.scrollToBottom()"
        x-cloak
    >
        {{ __('site.chat.new_messages', ['count' => $newMessageCount]) }}
    </button>

    <form class="community-chat__composer" wire:submit="postMessage">
        <label class="sr-only" for="community-chat-body">{{ __('site.chat.body') }}</label>
        <textarea
            id="community-chat-body"
            class="form-control"
            rows="3"
            maxlength="500"
            wire:model="body"
            placeholder="{{ $isMuted ? __('site.chat.placeholder_muted') : __('site.chat.placeholder') }}"
            @disabled($isMuted)
            x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.set('body', $event.target.value).then(() => $wire.postMessage()) }"
        ></textarea>
        @error('body')
            <p role="alert" class="community-chat__error">{{ $message }}</p>
        @enderror
        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" @disabled($isMuted)>
            <span wire:loading.remove wire:target="postMessage">{{ __('site.chat.send') }}</span>
            <span wire:loading wire:target="postMessage">{{ __('site.chat.sending') }}</span>
        </button>
    </form>
</section>
