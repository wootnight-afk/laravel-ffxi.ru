<?php

use App\Enums\ActivityType;
use App\Events\ChatMessageSentForActivity;
use App\Listeners\RecordChatMessageActivity;
use App\Livewire\ChatRoom;
use App\Models\Activity;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\SettingsRepository;
use Database\Seeders\DashboardWidgetSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function d4ChatUser(string $role = 'user', bool $verified = true, array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => $verified ? now() : null,
    ], $overrides));
    $user->assignRole($role);

    return $user;
}

function d4ChatMessage(User $user, string $body, array $overrides = []): ChatMessage
{
    return ChatMessage::create(array_merge([
        'user_id' => $user->id,
        'body' => $body,
    ], $overrides));
}

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('chat-message:');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(DashboardWidgetSeeder::class);
    Notification::fake();
    app(SettingsRepository::class)->set('chat_enabled', true);
});

it('places the chat before events and activity on the dashboard', function () {
    $this->actingAs(d4ChatUser())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Общий чат', 'Ближайшие события', 'Активность']);
});

it('does not expose the chat dashboard to guests', function () {
    $this->get(route('players.dashboard'))->assertRedirect(route('login'));
});

it('renders the latest fifty messages in chronological order', function () {
    $author = d4ChatUser();
    d4ChatMessage($author, 'ChatLegacyAlpha');
    foreach (range(1, 55) as $number) {
        d4ChatMessage($author, "Chat history item {$number}");
    }

    Livewire::actingAs(d4ChatUser())
        ->test(ChatRoom::class)
        ->assertSee('Chat history item 6')
        ->assertSee('Chat history item 55')
        ->assertDontSee('ChatLegacyAlpha');
});

it('loads older messages and polls incrementally by id', function () {
    $author = d4ChatUser();
    foreach (range(1, 55) as $number) {
        d4ChatMessage($author, "Chat history item {$number}");
    }

    $room = Livewire::actingAs(d4ChatUser())->test(ChatRoom::class);
    $room->call('loadMore')->assertSee('Chat history item 5');

    $newMessage = d4ChatMessage($author, 'Incremental arrival');
    $room->call('pollMessages')->assertSee('Incremental arrival');
    expect($room->get('lastId'))->toBe($newMessage->id);
});

it('rejects unverified, unpermitted, banned and muted senders', function () {
    $unverified = d4ChatUser(verified: false);
    Livewire::actingAs($unverified)
        ->test(ChatRoom::class)
        ->set('body', 'Blocked message')
        ->call('postMessage')
        ->assertForbidden();

    $unpermitted = User::factory()->create(['email_verified_at' => now()]);
    Livewire::actingAs($unpermitted)
        ->test(ChatRoom::class)
        ->set('body', 'Blocked message')
        ->call('postMessage')
        ->assertForbidden();

    $banned = d4ChatUser(overrides: ['banned_until' => now()->addHour()]);
    Livewire::actingAs($banned)
        ->test(ChatRoom::class)
        ->set('body', 'Blocked message')
        ->call('postMessage')
        ->assertForbidden();

    $muted = d4ChatUser(overrides: ['chat_banned_until' => now()->addHour()]);
    Livewire::actingAs($muted)
        ->test(ChatRoom::class)
        ->set('body', 'Blocked message')
        ->call('postMessage')
        ->assertForbidden();

    expect(ChatMessage::query()->count())->toBe(0);
});

it('enforces the configured chat enabled setting server-side', function () {
    $user = d4ChatUser();
    app(SettingsRepository::class)->set('chat_enabled', false);

    Livewire::actingAs($user)
        ->test(ChatRoom::class)
        ->assertNotFound();
});

it('sends a message and rejects text beyond five hundred characters', function () {
    $user = d4ChatUser();
    $room = Livewire::actingAs($user)->test(ChatRoom::class);

    $room->set('body', str_repeat('a', 500))
        ->call('postMessage')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('activities', 1);

    $room->set('body', str_repeat('b', 501))
        ->call('postMessage')
        ->assertHasErrors('body');

    expect(ChatMessage::query()->count())->toBe(1);
});

it('applies the five messages per thirty seconds rate limit', function () {
    $user = d4ChatUser();
    $room = Livewire::actingAs($user)->test(ChatRoom::class);

    foreach (range(1, 5) as $number) {
        $room->set('body', "Rate-limited chat message {$number}")
            ->call('postMessage')
            ->assertHasNoErrors();
    }

    $room->set('body', 'Rate limit overflow')
        ->call('postMessage')
        ->assertHasErrors('body');

    expect(ChatMessage::query()->count())->toBe(5);
});

it('escapes message markup and shows deleted messages as tombstones', function () {
    $author = d4ChatUser();
    $viewer = d4ChatUser();
    $message = d4ChatMessage($author, '<script>alert(1)</script>');

    Livewire::actingAs($viewer)
        ->test(ChatRoom::class)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);

    $admin = d4ChatUser('admin');
    Livewire::actingAs($admin)
        ->test(ChatRoom::class)
        ->set('deleteReason', 'Spam')
        ->call('deleteMessage', $message->id)
        ->assertSee('сообщение удалено')
        ->assertDontSee('alert(1)');

    expect($message->fresh()->is_deleted)->toBeTrue()
        ->and($message->fresh()->body)->toBe('')
        ->and($message->fresh()->deleted_reason)->toBe('Spam');
});

it('records chat activity once per message without storing its text', function () {
    $author = d4ChatUser();
    $message = d4ChatMessage($author, 'private chat text must not be stored in activity');
    $event = new ChatMessageSentForActivity($message, $author);
    $listener = app(RecordChatMessageActivity::class);

    $listener->handle($event);
    $listener->handle($event);

    $activity = Activity::query()
        ->where('type', ActivityType::ChatMessage->value)
        ->firstOrFail();

    expect($activity->subject_id)->toBe($message->id)
        ->and($activity->subject_type)->toBe($message->getMorphClass())
        ->and($activity->data)->toBe(['chat_context' => 'community'])
        ->and(Activity::query()->where('type', ActivityType::ChatMessage->value)->count())->toBe(1);
});

it('uses the normalized polling interval without one-second polling', function () {
    $user = d4ChatUser();
    app(SettingsRepository::class)->set('chat_polling_interval', 1);

    Livewire::actingAs($user)
        ->test(ChatRoom::class)
        ->assertSet('pollingInterval', 3);
});
