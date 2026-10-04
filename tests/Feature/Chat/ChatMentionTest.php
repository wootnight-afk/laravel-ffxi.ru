<?php

use App\Livewire\ChatRoom;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\ChatMentionNotification;
use App\Services\ChatMentionParser;
use Database\Seeders\DashboardWidgetSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function d4MentionUser(string $name, bool $profilePublic = true): User
{
    $user = User::factory()->create([
        'name' => $name,
        'email_verified_at' => now(),
        'is_profile_public' => $profilePublic,
    ]);
    $user->assignRole('user');

    return $user;
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(DashboardWidgetSeeder::class);
    Notification::fake();
});

it('notifies existing mentioned users but not the sender or unknown handles', function () {
    $sender = d4MentionUser('SenderChat1');
    $mentioned = d4MentionUser('MentionedChat2');
    $body = 'Hello @MentionedChat2 @SenderChat1 @UnknownChat3';

    Livewire::actingAs($sender)
        ->test(ChatRoom::class)
        ->set('body', $body)
        ->call('postMessage')
        ->assertHasNoErrors();

    Notification::assertSentTo($mentioned, ChatMentionNotification::class);
    Notification::assertNotSentTo($sender, ChatMentionNotification::class);
    expect(ChatMessage::query()->firstOrFail()->body)->toBe($body);
});

it('limits mention notifications to five distinct users', function () {
    $sender = d4MentionUser('SenderChat11');
    $recipients = [];
    $handles = [];

    foreach (range(1, 6) as $number) {
        $name = "MentionChat{$number}";
        $recipients[] = d4MentionUser($name);
        $handles[] = "@{$name}";
    }

    $body = implode(' ', $handles);
    $resolved = app(ChatMentionParser::class)->recipients($body, $sender);

    expect($resolved)->toHaveCount(5);

    Livewire::actingAs($sender)
        ->test(ChatRoom::class)
        ->set('body', $body)
        ->call('postMessage');

    foreach (array_slice($recipients, 0, 5) as $recipient) {
        Notification::assertSentTo($recipient, ChatMentionNotification::class);
    }
    Notification::assertNotSentTo($recipients[5], ChatMentionNotification::class);
});

it('renders a closed-profile mention as highlighted text without a link', function () {
    $sender = d4MentionUser('SenderChat21');
    $private = d4MentionUser('PrivateChat22', false);
    ChatMessage::create([
        'user_id' => $sender->id,
        'body' => "Hello @{$private->name}",
    ]);

    $this->actingAs($sender)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee("@{$private->name}", false)
        ->assertDontSee(route('players.show', $private->name), false);
});

it('uses the chat mention notification database channel and omits message text', function () {
    $sender = d4MentionUser('SenderChat31');
    $mentioned = d4MentionUser('MentionedChat32');
    $message = ChatMessage::create([
        'user_id' => $sender->id,
        'body' => "Hello @{$mentioned->name} private text",
    ]);
    $notification = new ChatMentionNotification($message, $sender);

    expect($notification->via($mentioned))->toBe(['database'])
        ->and($notification->toArray($mentioned))
        ->toMatchArray([
            'message_id' => $message->id,
            'chat_context' => 'community',
            'sender' => ['id' => $sender->id, 'name' => $sender->name],
        ])
        ->and($notification->toArray($mentioned))->not->toHaveKey('body');
});
