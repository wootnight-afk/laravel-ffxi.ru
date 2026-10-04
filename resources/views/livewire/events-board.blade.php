<section>
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px;">
        <h2 class="section-title" style="margin: 0;">Ближайшие события</h2>
        <label>
            <span class="sr-only">Фильтр по типу</span>
            <select class="form-control" wire:model.live="type" aria-label="Фильтр событий по типу">
                <option value="">Все типы</option>
                @foreach ($types as $eventType)
                    <option value="{{ $eventType->key }}">{{ $eventType->title }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if ($events->isEmpty())
        <p class="news-meta">На ближайшие 14 дней событий нет.</p>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 12px;">
            @foreach ($events as $event)
                <article class="news-item" style="padding: 14px; border: 1px solid var(--border-color); border-radius: 8px;">
                    <p class="news-meta">
                        {{ $event->type?->icon }} {{ $event->type?->title ?? 'Событие' }}
                        · {{ $event->starts_at->copy()->setTimezone($timezone)->format('d.m.Y H:i') }}
                    </p>
                    <h3 style="font-size: 17px;">
                        <a href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
                    </h3>
                    <p class="news-meta">
                        Организатор: {{ $event->user?->name ?? 'неизвестен' }}
                        · {{ $event->location ?: 'место не указано' }}
                    </p>
                    @livewire('event-signup', ['event' => $event], key('event-signup-'.$event->id))
                </article>
            @endforeach
        </div>
    @endif

    @auth
        @can('create', \App\Models\Event::class)
            <p style="margin-top: 16px;"><a href="{{ route('events.create') }}">+ Создать событие</a></p>
        @endcan
    @endauth

    <p style="margin-top: 12px;"><a href="{{ route('events.index') }}">Все события →</a></p>
</section>
