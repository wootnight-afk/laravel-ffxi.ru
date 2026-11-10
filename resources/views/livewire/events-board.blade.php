<section>
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px;">
        <h2 class="section-title" style="margin: 0;">{{ __('site.events.upcoming') }}</h2>
        <label>
            <span class="sr-only">{{ __('site.events.type_filter') }}</span>
            <select class="form-control" wire:model.live="type" aria-label="{{ __('site.events.type_filter_aria') }}">
                <option value="">{{ __('site.events.all_types') }}</option>
                @foreach ($types as $eventType)
                    <option value="{{ $eventType->key }}">{{ $eventType->title }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if ($events->isEmpty())
        <p class="news-meta">{{ __('site.events.empty_upcoming') }}</p>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 12px;">
            @foreach ($events as $event)
                <article class="news-item" style="padding: 14px; border: 1px solid var(--border-color); border-radius: 8px;">
                    <p class="news-meta">
                        {{ $event->type?->icon }} {{ $event->type?->title ?? __('site.events.fallback_type') }}
                        · @include('events._time', ['event' => $event])
                    </p>
                    <h3 style="font-size: 17px;">
                        <a href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
                    </h3>
                    <p class="news-meta">
                        {{ __('site.events.organizer') }} {{ $event->user?->name ?? __('site.common.unknown') }}
                        · {{ $event->location ?: __('site.events.location_missing_lower') }}
                    </p>
                    @livewire('event-signup', ['event' => $event], key('event-signup-'.$event->id))
                </article>
            @endforeach
        </div>
    @endif

    @auth
        @can('create', \App\Models\Event::class)
            <p style="margin-top: 16px;"><a href="{{ route('events.create') }}">{{ __('site.events.create') }}</a></p>
        @endcan
    @endauth

    <p style="margin-top: 12px;"><a href="{{ route('events.index') }}">{{ __('site.events.all') }}</a></p>
</section>
