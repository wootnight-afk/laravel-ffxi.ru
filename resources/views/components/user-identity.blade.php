@props(['user', 'showLink' => false, 'showAvatar' => false, 'showRank' => false])

<span
    class="user-identity"
    style="display: inline-flex; align-items: center; gap: 8px;"
>
    @if ($showAvatar)
        <x-avatar :user="$user" :size="32" />
    @endif

    @if ($showLink && Route::has('players.show'))
        <a href="{{ route('players.show', $user->name) }}">{{ $user->name }}</a>
    @else
        <span>{{ $user->name }}</span>
    @endif

    @if ($showRank)
        <x-user-rank :user="$user" :compact="true" />
    @endif
</span>
