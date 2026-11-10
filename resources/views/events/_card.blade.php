<article class="news-item" style="padding: 18px; border: 1px solid var(--border-color); border-radius: 8px;">
    <div class="news-meta" style="margin-bottom: 8px;">
        <span>{{ $event->type?->icon }} {{ $event->type?->title ?? __('site.events.fallback_type') }}</span>
        <span>@include('events._time', ['event' => $event])</span>
    </div>
    <h2 style="font-size: 18px; margin: 0 0 8px;">
        <a href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
    </h2>
    <p class="news-meta" style="margin: 0 0 8px;">
        {{ __('site.events.organizer') }} {{ $event->user?->name ?? __('site.common.unknown') }}
    </p>
    <p class="news-meta">
        {{ $event->location ?: __('site.events.location_missing') }}
        · {{ trans_choice('site.events.participants_count', $event->joined_count) }}{{ $event->max_participants === null ? ' / ∞' : '/'.$event->max_participants }}
    </p>
</article>
