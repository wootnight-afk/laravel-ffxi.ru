<?php

namespace App\Services;

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Events\CommunityEventCreated;
use App\Events\CommunityEventJoined;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventParticipantJoinedNotification;
use App\Notifications\EventParticipantLeftNotification;
use App\Notifications\EventUpdatedNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

class EventService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $leader, array $data): Event
    {
        $event = DB::transaction(function () use ($leader, $data): Event {
            $event = Event::create([
                ...$data,
                'user_id' => $leader->id,
                'status' => EventStatus::Planned,
            ]);

            $event->participants()->create([
                'user_id' => $leader->id,
                'status' => EventParticipantStatus::Joined,
                'joined_at' => now(),
                'left_at' => null,
            ]);

            return $event;
        });

        DB::afterCommit(fn () => event(new CommunityEventCreated($event, $leader)));

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Event $event, array $data): Event
    {
        if ($event->status !== EventStatus::Planned) {
            throw ValidationException::withMessages([
                'event' => 'Изменять можно только запланированное событие.',
            ]);
        }

        $changedFields = $this->changedFields($event, $data);
        $started = $event->starts_at->lessThanOrEqualTo(now());
        $allowedAfterStart = ['description', 'location'];
        $forbiddenFields = $started
            ? array_values(array_intersect($changedFields, array_diff(array_keys($data), $allowedAfterStart)))
            : [];

        if ($forbiddenFields !== []) {
            throw ValidationException::withMessages([
                $forbiddenFields[0] => 'После начала события это поле изменить нельзя.',
            ]);
        }

        $significantFields = [
            'title',
            'starts_at',
            'duration_minutes',
            'max_participants',
            'registration_close',
            'type_id',
        ];
        $significantChanges = array_values(array_intersect($changedFields, $significantFields));
        $notifyParticipants = ! $started
            && $significantChanges !== []
            && $event->participants()
                ->where('status', EventParticipantStatus::Joined)
                ->where('user_id', '!=', $event->user_id)
                ->exists();

        $event = DB::transaction(function () use ($event, $data): Event {
            $event->fill($data)->save();

            return $event->refresh();
        });

        if ($notifyParticipants) {
            $recipients = $this->joinedRecipients($event, $actor);
            $this->sendNotification(
                $recipients,
                new EventUpdatedNotification($event, $actor, $significantChanges),
                'event.updated',
                $event,
            );
        }

        return $event;
    }

    public function cancel(User $actor, Event $event, ?string $reason = null): Event
    {
        if ($event->status !== EventStatus::Planned) {
            throw ValidationException::withMessages([
                'event' => 'Отменить можно только запланированное событие.',
            ]);
        }

        $recipients = $this->joinedRecipients($event, $actor);

        $event = DB::transaction(function () use ($event): Event {
            $event->forceFill(['status' => EventStatus::Cancelled])->save();

            return $event->refresh();
        });

        $this->sendNotification(
            $recipients,
            new EventCancelledNotification($event, $actor, $reason),
            'event.cancelled',
            $event,
        );

        return $event;
    }

    public function join(User $actor, Event $event): EventParticipant
    {
        [$participant, $shouldNotifyLeader] = DB::transaction(function () use ($actor, $event): array {
            $lockedEvent = Event::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertJoinable($lockedEvent);

            $existing = EventParticipant::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $actor->id)
                ->first();

            if ($existing?->status === EventParticipantStatus::Joined) {
                return [$existing, false];
            }

            $joinedCount = $lockedEvent->participants()
                ->where('status', EventParticipantStatus::Joined)
                ->count();

            if ($lockedEvent->max_participants !== null && $joinedCount >= $lockedEvent->max_participants) {
                throw ValidationException::withMessages([
                    'event' => 'Свободных мест нет.',
                ]);
            }

            if ($existing !== null) {
                $existing->forceFill([
                    'status' => EventParticipantStatus::Joined,
                    'joined_at' => now(),
                    'left_at' => null,
                ])->save();

                return [$existing->refresh(), true];
            }

            return [$lockedEvent->participants()->create([
                'user_id' => $actor->id,
                'status' => EventParticipantStatus::Joined,
                'joined_at' => now(),
                'left_at' => null,
            ]), true];
        });

        if ($shouldNotifyLeader && $event->user_id !== null && $event->user_id !== $actor->id) {
            $leader = User::query()->find($event->user_id);

            if ($leader !== null) {
                $this->sendNotification(
                    collect([$leader]),
                    new EventParticipantJoinedNotification($event, $actor),
                    'event.participant_joined',
                    $event,
                );
            }
        }

        if ($shouldNotifyLeader) {
            DB::afterCommit(fn () => event(new CommunityEventJoined($event, $actor)));
        }

        return $participant;
    }

    public function leave(User $actor, Event $event): void
    {
        $didLeave = DB::transaction(function () use ($actor, $event): bool {
            $participant = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if ($participant === null || $participant->status === EventParticipantStatus::Left) {
                return false;
            }

            $participant->forceFill([
                'status' => EventParticipantStatus::Left,
                'left_at' => now(),
            ])->save();

            return true;
        });

        if (! $didLeave || $event->user_id === null || $event->user_id === $actor->id) {
            return;
        }

        $leader = User::query()->find($event->user_id);

        if ($leader !== null) {
            $this->sendNotification(
                collect([$leader]),
                new EventParticipantLeftNotification($event, $actor),
                'event.participant_left',
                $event,
            );
        }
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function joinedRecipients(Event $event, User $except): EloquentCollection
    {
        return User::query()
            ->whereHas('eventParticipations', fn ($query) => $query
                ->where('event_id', $event->id)
                ->where('status', EventParticipantStatus::Joined))
            ->whereKeyNot($except->getKey())
            ->get();
    }

    /**
     * @param  iterable<User>  $recipients
     */
    private function sendNotification(
        iterable $recipients,
        \Illuminate\Notifications\Notification $notification,
        string $context,
        Event $event,
    ): void {
        try {
            Notification::send($recipients, $notification);
        } catch (Throwable $exception) {
            Log::error('Event notification delivery failed.', [
                'context' => $context,
                'event_id' => $event->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function assertJoinable(Event $event): void
    {
        if ($event->status !== EventStatus::Planned) {
            throw ValidationException::withMessages([
                'event' => 'Записаться можно только на запланированное событие.',
            ]);
        }

        if ($event->starts_at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'event' => 'Регистрация на событие уже закрыта.',
            ]);
        }

        if ($event->registration_close !== null && $event->registration_close->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'event' => 'Регистрация на событие уже закрыта.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function changedFields(Event $event, array $data): array
    {
        $changed = [];

        foreach ($data as $field => $value) {
            if (! $this->valuesEqual($field, $event->getAttribute($field), $value)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    private function valuesEqual(string $field, mixed $current, mixed $incoming): bool
    {
        if (in_array($field, ['starts_at', 'registration_close'], true)) {
            if ($current === null || $incoming === null) {
                return $current === $incoming;
            }

            return $current->equalTo(Carbon::parse($incoming, 'UTC'));
        }

        if (in_array($field, ['type_id', 'duration_minutes', 'max_participants'], true)) {
            if ($current === null || $incoming === null) {
                return $current === $incoming;
            }

            return (int) $current === (int) $incoming;
        }

        return $current === $incoming;
    }
}
