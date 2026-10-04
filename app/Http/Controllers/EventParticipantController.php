<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventParticipantController extends Controller
{
    public function __construct(
        private readonly EventService $events,
    ) {}

    public function join(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('join', $event);
        $this->events->join($request->user(), $event);

        return back()->with('status', 'Вы записаны на событие.');
    }

    public function leave(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('leave', $event);
        $this->events->leave($request->user(), $event);

        return back()->with('status', 'Вы вышли из события.');
    }
}
