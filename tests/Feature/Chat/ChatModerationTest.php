<?php

use App\Livewire\ChatRoom;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\ChatBannedNotification;
use App\Notifications\ChatUnbannedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function d4ModerationUser(string $role = 'user'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Notification::fake();
});

it('allows only chat.moderate users to delete messages', function () {
    $author = d4ModerationUser();
    $message = ChatMessage::create(['user_id' => $author->id, 'body' => 'Moderation target']);
    $editor = d4ModerationUser('editor');

    Livewire::actingAs($editor)
        ->test(ChatRoom::class)
        ->call('deleteMessage', $message->id)
        ->assertForbidden();

    $admin = d4ModerationUser('admin');
    Livewire::actingAs($admin)
        ->test(ChatRoom::class)
        ->call('deleteMessage', $message->id)
        ->assertHasNoErrors();

    expect($message->fresh()->is_deleted)->toBeTrue()
        ->and($message->fresh()->deleted_by_user_id)->toBe($admin->id);
});

it('allows chat moderators to mute themselves and other admins', function () {
    $admin = d4ModerationUser('admin');
    $otherAdmin = d4ModerationUser('admin');

    Livewire::actingAs($admin)
        ->test(ChatRoom::class)
        ->set('banDuration', '1h')
        ->call('banUser', $admin->id)
        ->assertHasNoErrors();

    $admin->refresh();
    expect($admin->isChatBanned())->toBeTrue()
        ->and($admin->chat_banned_permanently)->toBeFalse();
    Notification::assertSentTo($admin, ChatBannedNotification::class);

    Livewire::actingAs($otherAdmin)
        ->test(ChatRoom::class)
        ->set('banDuration', 'permanent')
        ->call('banUser', $admin->id)
        ->assertHasNoErrors();

    expect($admin->fresh()->chat_banned_permanently)->toBeTrue()
        ->and($admin->fresh()->isChatBanned())->toBeTrue();
});

it('supports temporary mute durations and unmute notifications', function () {
    $admin = d4ModerationUser('admin');
    $target = d4ModerationUser();

    Livewire::actingAs($admin)
        ->test(ChatRoom::class)
        ->set('banDuration', '7d')
        ->call('banUser', $target->id);

    $target->refresh();
    expect($target->chat_banned_until->between(now()->addDays(6), now()->addDays(8)))->toBeTrue()
        ->and($target->chat_banned_permanently)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ChatRoom::class)
        ->call('unbanUser', $target->id);

    expect($target->fresh()->chat_banned_until)->toBeNull()
        ->and($target->fresh()->chat_banned_permanently)->toBeFalse();
    Notification::assertSentTo($target, ChatUnbannedNotification::class);
});

it('rejects moderator actions from users without chat.moderate', function () {
    $user = d4ModerationUser();
    $target = d4ModerationUser();

    Livewire::actingAs($user)
        ->test(ChatRoom::class)
        ->call('banUser', $target->id)
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test(ChatRoom::class)
        ->call('unbanUser', $target->id)
        ->assertForbidden();
});

it('uses database-only notifications for mute and unmute actions', function () {
    $moderator = d4ModerationUser('admin');
    $target = d4ModerationUser();

    $room = Livewire::actingAs($moderator)->test(ChatRoom::class);
    $room->call('banUser', $target->id);
    $room->call('unbanUser', $target->id);

    Notification::assertSentTo($target, ChatBannedNotification::class);
    Notification::assertSentTo($target, ChatUnbannedNotification::class);
    expect((new ChatBannedNotification($moderator, now()->toIso8601String(), false))->via($target))
        ->toBe(['database'])
        ->and((new ChatUnbannedNotification($moderator))->via($target))->toBe(['database']);
});
