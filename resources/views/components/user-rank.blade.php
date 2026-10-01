@props(['show' => false, 'rank' => null, 'compact' => true])

@if ($show && $rank !== null)
    @if ($compact)
        <span
            class="user-rank user-rank--compact"
            title="{{ $rank->title }} ({{ $rank->rating }})"
            style="font-size: 12px; color: var(--text-muted);"
        >
            {{ $rank->icon }} {{ $rank->rating }}
        </span>
    @else
        <span
            class="user-rank user-rank--full"
            style="display: inline-flex; align-items: center; gap: 4px; font-size: 13px;"
        >
            <span>{{ $rank->icon }}</span>
            <span style="font-weight: 600;">{{ $rank->title }}</span>
            <span style="color: var(--text-muted);">({{ $rank->rating }})</span>
        </span>
    @endif
@endif
