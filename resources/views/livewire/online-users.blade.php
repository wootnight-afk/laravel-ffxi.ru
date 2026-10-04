<section class="online-users" wire:poll.60s="refreshOnlineUsers">
    <h2 class="section-title">Игроки онлайн</h2>
    <a class="online-users__count" href="{{ route('players.directory') }}">
        {{ $onlineCount }} {{ trans_choice('игрок|игрока|игроков', $onlineCount) }} онлайн
    </a>

    @if ($onlineUsers->isNotEmpty())
        <ul class="online-users__list" aria-label="Игроки онлайн">
            @foreach ($onlineUsers as $onlineUser)
                @php
                    $profileUrl = app(\App\Services\ActivitySubjectResolver::class)->actorLinkable($onlineUser, $viewer)
                        ? route('players.show', $onlineUser->name)
                        : null;
                @endphp
                <li wire:key="online-player-{{ $onlineUser->id }}">
                    @if ($profileUrl)
                        <a href="{{ $profileUrl }}" aria-label="{{ $onlineUser->name }}">
                            <x-avatar :user="$onlineUser" :size="40" />
                        </a>
                    @else
                        <x-avatar :user="$onlineUser" :size="40" />
                    @endif
                    <span>{{ $onlineUser->name }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="news-meta">Сейчас игроков нет онлайн.</p>
    @endif
</section>
