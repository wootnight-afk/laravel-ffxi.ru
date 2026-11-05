<?php

namespace App\Livewire;

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventType;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class EventsBoard extends Component
{
    public string $type = '';

    public function render(): View
    {
        $query = Event::query()
            ->where('status', EventStatus::Planned)
            ->whereBetween('starts_at', [now(), now()->addDays(14)])
            ->with(['type', 'user'])
            ->withCount([
                'participants as joined_count' => fn ($participants) => $participants
                    ->where('status', EventParticipantStatus::Joined),
            ])
            ->orderBy('starts_at');

        if ($this->type !== '') {
            $query->whereHas('type', fn ($type) => $type
                ->where('key', $this->type)
                ->where('is_active', true));
        }

        return view('livewire.events-board', [
            'events' => $query->get(),
            'types' => EventType::query()->active()->orderBy('sort_order')->get(),
        ]);
    }
}
