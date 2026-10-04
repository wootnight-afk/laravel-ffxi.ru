@php
    $activity = $group['activity'];
    $items = $group['items'];
    $actor = $activity->actor;
    $subjectUrl = app(\App\Services\ActivitySubjectResolver::class)->url($activity, $viewer);
    $snapshot = $activity->data['title']
        ?? $activity->data['target_title']
        ?? $activity->data['caption']
        ?? $activity->data['album_title']
        ?? $activity->data['name']
        ?? null;
@endphp

<article class="news-item" style="display: flex; align-items: flex-start; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--border-color);">
    <span aria-hidden="true" style="font-size: 20px;">{{ $activity->type->icon() }}</span>
    <div style="min-width: 0; flex: 1;">
        <p style="margin: 0 0 4px;">
            <x-activity-actor :actor="$actor" :viewer="$viewer" />
            {{ $activity->type->label() }}
            @if (count($items) > 1)
                <span class="news-meta">({{ count($items) }} раза)</span>
            @endif
            @if ($snapshot)
                @if ($subjectUrl)
                    <a href="{{ $subjectUrl }}">{{ $snapshot }}</a>
                @else
                    <span>{{ $snapshot }}</span>
                @endif
            @elseif ($subjectUrl)
                <a href="{{ $subjectUrl }}">перейти</a>
            @endif
        </p>
        <time class="news-meta" datetime="{{ $activity->created_at->toIso8601String() }}">
            {{ $activity->created_at->diffForHumans() }}
        </time>
    </div>
</article>
