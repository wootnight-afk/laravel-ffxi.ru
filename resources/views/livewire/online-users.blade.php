<section class="online-users" wire:poll.60s="refreshOnlineUsers">
    <h2 class="section-title">{{ __('site.online.title') }}</h2>
    <a class="online-users__count" href="{{ route('players.directory') }}">
        {{ trans_choice('site.online.count', $onlineCount) }}
    </a>

    @if ($onlineUsers->isNotEmpty())
        <ul class="online-users__list" aria-label="{{ __('site.online.title') }}">
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
        <p class="news-meta">{{ __('site.online.empty') }}</p>
    @endif
</section>
