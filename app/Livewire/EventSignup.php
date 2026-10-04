<?php

namespace App\Livewire;

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class EventSignup extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function join(EventService $events): void
    {
        $user = $this->authenticatedUser();
        Gate::forUser($user)->authorize('join', $this->event);
        $events->join($user, $this->event);
        $this->event->refresh();
    }

    public function leave(EventService $events): void
    {
        $user = $this->authenticatedUser();
        Gate::forUser($user)->authorize('leave', $this->event);
        $events->leave($user, $this->event);
        $this->event->refresh();
    }

    public function render(): View
    {
        $joinedCount = $this->event->participants()
            ->where('status', EventParticipantStatus::Joined)
            ->count();

        $user = Auth::user();
        $participation = $user === null
            ? null
            : EventParticipant::query()
                ->where('event_id', $this->event->id)
                ->where('user_id', $user->id)
                ->first();

        $joinClosed = $this->event->status !== EventStatus::Planned
            || $this->event->starts_at->lessThanOrEqualTo(now())
            || ($this->event->registration_close !== null
                && $this->event->registration_close->lessThanOrEqualTo(now()));
        $eventFull = $this->event->max_participants !== null
            && $joinedCount >= $this->event->max_participants;

        return view('livewire.event-signup', [
            'participation' => $participation,
            'joinedCount' => $joinedCount,
            'canJoin' => $user !== null && Gate::forUser($user)->allows('join', $this->event),
            'canLeave' => $user !== null && Gate::forUser($user)->allows('leave', $this->event),
            'joinClosed' => $joinClosed,
            'eventFull' => $eventFull,
        ]);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
