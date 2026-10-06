<?php

declare(strict_types=1);

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\EventResource\RelationManagers\ParticipantsRelationManager;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\User;
use Livewire\Livewire;

function makeEventPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeEventRecord(User $leader, array $overrides = []): Event
{
    $event = Event::create(array_merge([
        'user_id' => $leader->id,
        'title' => 'Event '.uniqid(),
        'description' => 'A sufficiently long event description.',
        'starts_at' => now()->addDays(2),
        'status' => EventStatus::Planned,
    ], $overrides));

    EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => $leader->id,
        'status' => EventParticipantStatus::Joined,
        'joined_at' => now(),
        'left_at' => null,
    ]);

    return $event;
}

// ------------------------------------------------------------------
// Access
// ------------------------------------------------------------------

it('allows editors to access the events resource', function () {
    $editor = makeEventPanelUser('editor');

    $this->actingAs($editor)->get('/admin/events')->assertOk();
});

it('denies regular users access to the events resource', function () {
    $user = makeEventPanelUser('user');

    $this->actingAs($user)->get('/admin/events')->assertForbidden();
});

// ------------------------------------------------------------------
// Create via EventService
// ------------------------------------------------------------------

it('creates an event through the service and registers the leader as participant', function () {
    $editor = makeEventPanelUser('editor');
    $type = EventType::create(['key' => 'raid-'.uniqid(), 'title' => 'Raid', 'is_active' => true]);

    Livewire::actingAs($editor)
        ->test(CreateEvent::class)
        ->set('data.title', 'Nyzul Isle run')
        ->set('data.type_id', $type->id)
        ->set('data.description', 'Meet at the docks.')
        ->set('data.starts_at', now()->addDay()->format('Y-m-d H:i:s'))
        ->call('create')
        ->assertHasNoErrors();

    $event = Event::query()->latest('id')->firstOrFail();

    expect($event->user_id)->toBe($editor->id)
        ->and($event->status)->toBe(EventStatus::Planned)
        ->and($event->type_id)->toBe($type->id)
        ->and($event->participants()->where('user_id', $editor->id)->where('status', EventParticipantStatus::Joined)->exists())->toBeTrue();
});

// ------------------------------------------------------------------
// Update via EventService
// ------------------------------------------------------------------

it('updates an event description through the service', function () {
    $editor = makeEventPanelUser('editor');
    $event = makeEventRecord($editor, ['starts_at' => now()->addDays(3)]);

    Livewire::actingAs($editor)
        ->test(EditEvent::class, ['record' => $event->getKey()])
        ->set('data.description', 'Updated description text.')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->description)->toBe('Updated description text.');
});

// ------------------------------------------------------------------
// Status transitions via EventService
// ------------------------------------------------------------------

it('cancels a planned event through the table action', function () {
    $editor = makeEventPanelUser('editor');
    $event = makeEventRecord($editor);

    Livewire::actingAs($editor)
        ->test(ListEvents::class)
        ->mountTableAction('cancel', $event)
        ->set('mountedActions.0.data.reason', 'No time')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($event->refresh()->status)->toBe(EventStatus::Cancelled);
});

it('does not offer the cancel action for a cancelled event', function () {
    $editor = makeEventPanelUser('editor');
    $event = makeEventRecord($editor, ['status' => EventStatus::Cancelled]);

    Livewire::actingAs($editor)
        ->test(ListEvents::class)
        ->assertTableActionHidden('cancel', $event);
});

// ------------------------------------------------------------------
// Relations
// ------------------------------------------------------------------

it('lists participants of an event in the relation manager', function () {
    $editor = makeEventPanelUser('editor');
    $event = makeEventRecord($editor);
    $participant = $event->participants()->firstOrFail();

    Livewire::actingAs($editor)
        ->test(ParticipantsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditEvent::class,
        ])
        ->assertCanSeeTableRecords([$participant]);
});

it('filters events by status', function () {
    $editor = makeEventPanelUser('editor');
    $planned = makeEventRecord($editor);
    $cancelled = makeEventRecord($editor, ['status' => EventStatus::Cancelled]);

    Livewire::actingAs($editor)
        ->test(ListEvents::class)
        ->filterTable('status', EventStatus::Planned->value)
        ->assertCanSeeTableRecords([$planned])
        ->assertCanNotSeeTableRecords([$cancelled]);
});

// ------------------------------------------------------------------
// Export exclusion (R7)
// ------------------------------------------------------------------

it('does not expose an export action in the events resource', function () {
    $editor = makeEventPanelUser('editor');

    Livewire::actingAs($editor)
        ->test(ListEvents::class)
        ->assertTableActionDoesNotExist('export')
        ->assertTableBulkActionDoesNotExist('export');
});
