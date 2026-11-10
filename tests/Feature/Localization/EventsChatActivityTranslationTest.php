<?php

declare(strict_types=1);

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Enums\UserStatus;
use App\Livewire\ActivityBell;
use App\Livewire\ChatRoom;
use App\Livewire\OnlineUsers;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\User;
use Livewire\Livewire;

function l55User(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'status' => UserStatus::Active,
    ], $overrides));
    $user->assignRole('user');

    return $user;
}

function l55Event(User $leader, array $overrides = []): Event
{
    $type = EventType::query()->firstOrCreate(
        ['key' => 'party'],
        ['title' => 'Party', 'icon' => '⚔'],
    );

    $event = Event::create(array_merge([
        'user_id' => $leader->id,
        'type_id' => $type->id,
        'title' => 'Translation test event',
        'description' => 'A sufficiently detailed event description.',
        'location' => 'Jeuno',
        'starts_at' => now()->addDays(3),
        'duration_minutes' => 60,
        'status' => EventStatus::Planned,
    ], $overrides));

    EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => $leader->id,
        'status' => EventParticipantStatus::Joined,
        'joined_at' => now(),
        'left_at' => null,
    ]);

    return $event->refresh();
}

it('renders the events index in english and russian', function () {
    $this->withSession(['locale' => 'en'])
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('Events')
        ->assertSee('All types')
        ->assertDontSee('Ближайших событий пока нет.');

    $this->withSession(['locale' => 'ru'])
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('События')
        ->assertSee('Все типы')
        ->assertSee('Ближайших событий пока нет.');
});

it('renders the event page chrome in english', function () {
    $event = l55Event(l55User());

    EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => l55User()->id,
        'status' => EventParticipantStatus::Joined,
        'joined_at' => now(),
        'left_at' => null,
    ]);

    $this->withSession(['locale' => 'en'])
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Organizer:')
        ->assertSee('Participants')
        ->assertSee('Discussion')
        ->assertSee('2 participants')
        ->assertSee('All events')
        ->assertDontSee('Организатор:');
});

it('renders the event form in english', function () {
    $user = l55User();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('events.create'))
        ->assertOk()
        ->assertSee('Create event')
        ->assertSee('Timezone')
        ->assertSee('Duration, minutes')
        ->assertSee('Save')
        ->assertDontSee('Создать событие');
});

it('pluralizes participant counts for both locales', function () {
    expect(trans_choice('site.events.participants_count', 1, [], 'ru'))->toBe('1 участник')
        ->and(trans_choice('site.events.participants_count', 2, [], 'ru'))->toBe('2 участника')
        ->and(trans_choice('site.events.participants_count', 5, [], 'ru'))->toBe('5 участников')
        ->and(trans_choice('site.events.participants_count', 1, [], 'en'))->toBe('1 participant')
        ->and(trans_choice('site.events.participants_count', 2, [], 'en'))->toBe('2 participants');
});

it('pluralizes the online counter for both locales', function () {
    expect(trans_choice('site.online.count', 1, [], 'ru'))->toBe('1 игрок онлайн')
        ->and(trans_choice('site.online.count', 3, [], 'ru'))->toBe('3 игрока онлайн')
        ->and(trans_choice('site.online.count', 7, [], 'ru'))->toBe('7 игроков онлайн')
        ->and(trans_choice('site.online.count', 1, [], 'en'))->toBe('1 player online')
        ->and(trans_choice('site.online.count', 7, [], 'en'))->toBe('7 players online');
});

it('renders the online users widget in english', function () {
    $viewer = l55User();
    l55User(['last_seen_at' => now()]);

    Livewire::actingAs($viewer)
        ->test(OnlineUsers::class)
        ->assertSee('Players online')
        ->assertSee('1 player online');
});

it('renders the chat in english', function () {
    $user = l55User();

    Livewire::actingAs($user)
        ->test(ChatRoom::class)
        ->assertSee('General chat')
        ->assertSee('Write a message')
        ->assertDontSee('Общий чат');
});

it('renders the activity bell in english', function () {
    $user = l55User();

    Livewire::actingAs($user)
        ->test(ActivityBell::class)
        ->assertSee('Community')
        ->assertSee('Personal')
        ->assertDontSee('Сообщество');
});

it('renders the activity feed page in english', function () {
    $this->actingAs(l55User())
        ->withSession(['locale' => 'en'])
        ->get(route('activity.index'))
        ->assertOk()
        ->assertSee('Community activity')
        ->assertDontSee('Активность сообщества');
});

it('renders the activity feed page in russian', function () {
    $this->actingAs(l55User())
        ->withSession(['locale' => 'ru'])
        ->get(route('activity.index'))
        ->assertOk()
        ->assertSee('Активность сообщества')
        ->assertDontSee('Community activity');
});
