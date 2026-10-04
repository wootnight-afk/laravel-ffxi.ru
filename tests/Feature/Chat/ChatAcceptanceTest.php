<?php

use App\Livewire\ChatRoom;
use App\Models\ChatMessage;
use App\Models\User;
use Database\Seeders\DashboardWidgetSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function stage7AcceptanceChatUser(string $name, bool $profilePublic = true): User
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

it('renders an existing public-profile mention with highlighting and a profile link', function () {
    $sender = stage7AcceptanceChatUser('AcceptSender');
    $mentioned = stage7AcceptanceChatUser('AcceptMention');
    ChatMessage::query()->create([
        'user_id' => $sender->id,
        'body' => "Hello @{$mentioned->name}",
    ]);

    Livewire::actingAs($sender)
        ->test(ChatRoom::class)
        ->assertSee('community-chat__mention')
        ->assertSee(route('players.show', $mentioned->name), false)
        ->assertSee("@{$mentioned->name}");
});

it('renders a closed-profile mention as highlighted text without a profile link', function () {
    $sender = stage7AcceptanceChatUser('AcceptPrivateSender');
    $mentioned = stage7AcceptanceChatUser('AcceptPrivateMention', false);
    ChatMessage::query()->create([
        'user_id' => $sender->id,
        'body' => "Hello @{$mentioned->name}",
    ]);

    Livewire::actingAs($sender)
        ->test(ChatRoom::class)
        ->assertSee('community-chat__mention')
        ->assertDontSee(route('players.show', $mentioned->name), false)
        ->assertSee("@{$mentioned->name}");
});
