<?php

namespace App\Http\Controllers;

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\EventType;
use App\Services\ContentRenderer;
use App\Services\EventService;
use App\Services\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly ContentRenderer $contentRenderer,
        private readonly SettingsRepository $settings,
    ) {}

    public function index(Request $request): View
    {
        $typeKey = $request->query('type');
        $query = Event::query()
            ->where('status', EventStatus::Planned)
            ->where('starts_at', '>', now())
            ->with(['type', 'user'])
            ->withCount([
                'participants as joined_count' => fn ($participants) => $participants
                    ->where('status', EventParticipantStatus::Joined),
            ])
            ->orderBy('starts_at');

        if (is_string($typeKey) && $typeKey !== '') {
            $query->whereHas('type', fn ($type) => $type->where('key', $typeKey)->where('is_active', true));
        }

        return view('events.index', [
            'events' => $query->paginate(12)->withQueryString(),
            'types' => EventType::query()->active()->orderBy('sort_order')->get(),
            'selectedType' => is_string($typeKey) ? $typeKey : '',
            'timezone' => $this->displayTimezone(),
        ]);
    }

    public function show(Event $event): View
    {
        $event->load([
            'type',
            'user',
            'participants' => fn ($participants) => $participants
                ->where('status', EventParticipantStatus::Joined)
                ->with('user')
                ->orderBy('joined_at'),
        ]);
        $event->loadCount([
            'participants as joined_count' => fn ($participants) => $participants
                ->where('status', EventParticipantStatus::Joined),
        ]);

        $viewerParticipation = request()->user() === null
            ? null
            : $event->participants->firstWhere('user_id', request()->user()->id);

        return view('events.show', [
            'event' => $event,
            'descriptionHtml' => $this->contentRenderer->render($event->description),
            'viewerParticipation' => $viewerParticipation,
            'timezone' => $this->displayTimezone(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Event::class);

        return view('events.create', [
            'event' => new Event,
            'types' => EventType::query()->active()->orderBy('sort_order')->get(),
            'timezone' => $this->displayTimezone(),
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = $this->events->create($request->user(), $request->validated());

        return redirect()
            ->route('events.show', $event)
            ->with('status', 'Событие создано.');
    }

    public function edit(Event $event): View
    {
        Gate::authorize('update', $event);

        return view('events.edit', [
            'event' => $event,
            'types' => EventType::query()->active()->orderBy('sort_order')->get(),
            'timezone' => $this->displayTimezone(),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->events->update($request->user(), $event, $request->validated());

        return redirect()
            ->route('events.show', $event)
            ->with('status', 'Событие обновлено.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('cancel', $event);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->events->cancel($request->user(), $event, $validated['reason'] ?? null);

        return redirect()
            ->route('events.show', $event)
            ->with('status', 'Событие отменено.');
    }

    private function displayTimezone(): string
    {
        return $this->settings->string('timezone_display', 'Europe/Moscow');
    }
}
