<article class="news-item" style="padding: 18px; border: 1px solid var(--border-color); border-radius: 8px;">
    <div class="news-meta" style="margin-bottom: 8px;">
        <span>{{ $event->type?->icon }} {{ $event->type?->title ?? 'Событие' }}</span>
        <span>{{ $event->starts_at->copy()->setTimezone($timezone)->format('d.m.Y H:i') }}</span>
    </div>
    <h2 style="font-size: 18px; margin: 0 0 8px;">
        <a href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
    </h2>
    <p class="news-meta" style="margin: 0 0 8px;">
        Организатор: {{ $event->user?->name ?? 'неизвестен' }}
    </p>
    <p class="news-meta">
        {{ $event->location ?: 'Место не указано' }}
        · {{ $event->max_participants === null ? $event->joined_count.' участников / ∞' : $event->joined_count.'/'.$event->max_participants }}
    </p>
</article>
