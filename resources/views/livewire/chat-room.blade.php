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
    <header class="community-chat__header">
        <h2 class="section-title">Общий чат</h2>
    </header>

    @if ($isMuted)
        <p class="community-chat__notice" role="status">
            Вы заглушены
            @if (auth()->user()->chat_banned_permanently)
                навсегда.
            @else
                до {{ auth()->user()->chat_banned_until->timezone(config('app.timezone'))->format('d.m.Y H:i') }}.
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
            <p class="news-meta">В чате пока нет сообщений.</p>
        @else
            @php
                $previousDate = null;
            @endphp
            @foreach ($messages as $message)
                @php
                    $messageDate = $message->created_at->toDateString();
                    $dateLabel = $message->created_at->isToday()
                        ? 'Сегодня'
                        : ($message->created_at->isYesterday()
                            ? 'Вчера'
                            : $message->created_at->format('d.m.Y'));
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
                            <span class="user-identity user-deleted">[аккаунт удалён]</span>
                        @endif

                        @if ($message->is_deleted)
                            <span class="community-chat__deleted">сообщение удалено</span>
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

                        <time class="news-meta" datetime="{{ $message->created_at->toIso8601String() }}">
                            {{ $message->created_at->format('H:i') }}
                        </time>
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
                                        aria-label="Причина удаления"
                                        placeholder="Причина (необязательно)"
                                    >
                                    <button class="btn" type="submit" wire:loading.attr="disabled">Удалить</button>
                                </form>
                            @endif

                            @if ($message->user)
                                @if ($message->user->isChatBanned())
                                    <button class="btn" type="button" wire:click="unbanUser({{ $message->user->id }})" wire:loading.attr="disabled">
                                        Снять мут
                                    </button>
                                @else
                                    <form wire:submit="banUser({{ $message->user->id }})">
                                        <label class="sr-only" for="chat-ban-duration-{{ $message->id }}">Срок мута</label>
                                        <select id="chat-ban-duration-{{ $message->id }}" class="form-control" wire:model="banDuration">
                                            <option value="1h">1 час</option>
                                            <option value="1d">1 день</option>
                                            <option value="7d">7 дней</option>
                                            <option value="permanent">Навсегда</option>
                                        </select>
                                        <button class="btn" type="submit" wire:loading.attr="disabled">Заглушить</button>
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
            Загрузить предыдущие
        </button>
    @endif

    <button
        class="community-chat__new-pill"
        type="button"
        x-show="$wire.newMessageCount > 0"
        x-on:click="$wire.scrollToBottom()"
        x-cloak
    >
        ↓ Новые ({{ $newMessageCount }})
    </button>

    <form class="community-chat__composer" wire:submit="postMessage">
        <label class="sr-only" for="community-chat-body">Сообщение</label>
        <textarea
            id="community-chat-body"
            class="form-control"
            rows="3"
            maxlength="500"
            wire:model="body"
            placeholder="{{ $isMuted ? 'Отправка сообщений недоступна' : 'Напишите сообщение…' }}"
            @disabled($isMuted)
            x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.set('body', $event.target.value).then(() => $wire.postMessage()) }"
        ></textarea>
        @error('body')
            <p role="alert" class="community-chat__error">{{ $message }}</p>
        @enderror
        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" @disabled($isMuted)>
            <span wire:loading.remove wire:target="postMessage">Отправить</span>
            <span wire:loading wire:target="postMessage">Отправка…</span>
        </button>
    </form>
</section>
