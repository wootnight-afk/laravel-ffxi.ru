<?php

declare(strict_types=1);

use App\Filament\Resources\EventTypeResource\Pages\CreateEventType;
use App\Filament\Resources\EventTypeResource\Pages\EditEventType;
use App\Filament\Resources\EventTypeResource\Pages\ListEventTypes;
use App\Models\EventType;
use App\Models\User;
use Livewire\Livewire;

function makeEventTypePanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeEventTypeRecord(array $overrides = []): EventType
{
    return EventType::create(array_merge([
        'key' => 'type-'.uniqid(),
        'title' => 'Type '.uniqid(),
        'is_active' => true,
    ], $overrides));
}

// ------------------------------------------------------------------
// Access — admin-only gate (contract §3.2)
// ------------------------------------------------------------------

it('allows admins to access the event type resource', function () {
    $admin = makeEventTypePanelUser('admin');

    $this->actingAs($admin)->get('/admin/event-types')->assertOk();
});

it('denies editors access to the event type resource despite events.manage_any', function () {
    $editor = makeEventTypePanelUser('editor');

    expect($editor->can('events.manage_any'))->toBeTrue();

    $this->actingAs($editor)->get('/admin/event-types')->assertForbidden();
});

it('denies regular users access to the event type resource', function () {
    $user = makeEventTypePanelUser('user');

    $this->actingAs($user)->get('/admin/event-types')->assertForbidden();
});

// ------------------------------------------------------------------
// CRUD
// ------------------------------------------------------------------

it('creates an event type as admin', function () {
    $admin = makeEventTypePanelUser('admin');

    Livewire::actingAs($admin)
        ->test(CreateEventType::class)
        ->set('data.key', 'raid')
        ->set('data.title', 'Рейд')
        ->set('data.is_active', true)
        ->call('create')
        ->assertHasNoErrors();

    expect(EventType::query()->where('key', 'raid')->exists())->toBeTrue();
});

it('toggles is_active through the edit page', function () {
    $admin = makeEventTypePanelUser('admin');
    $type = makeEventTypeRecord(['is_active' => true]);

    Livewire::actingAs($admin)
        ->test(EditEventType::class, ['record' => $type->getKey()])
        ->set('data.is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($type->refresh()->is_active)->toBeFalse();
});

it('does not expose an export action in the event type resource', function () {
    $admin = makeEventTypePanelUser('admin');

    Livewire::actingAs($admin)
        ->test(ListEventTypes::class)
        ->assertTableActionDoesNotExist('export')
        ->assertTableBulkActionDoesNotExist('export');
});
