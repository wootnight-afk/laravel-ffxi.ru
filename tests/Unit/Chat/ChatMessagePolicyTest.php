<?php

use App\Models\ChatMessage;
use App\Models\User;
use App\Policies\ChatMessagePolicy;

it('requires verified permission and no account or chat ban to send', function () {
    $policy = new ChatMessagePolicy;

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('hasVerifiedEmail')->andReturn(true);
    $allowed->shouldReceive('can')->with('chat.participate')->andReturn(true);
    $allowed->shouldReceive('isBanned')->andReturn(false);
    $allowed->shouldReceive('isChatBanned')->andReturn(false);

    expect($policy->send($allowed))->toBeTrue();

    $chatBanned = Mockery::mock(User::class)->makePartial();
    $chatBanned->shouldReceive('hasVerifiedEmail')->andReturn(true);
    $chatBanned->shouldReceive('can')->with('chat.participate')->andReturn(true);
    $chatBanned->shouldReceive('isBanned')->andReturn(false);
    $chatBanned->shouldReceive('isChatBanned')->andReturn(true);

    expect($policy->send($chatBanned))->toBeFalse();
});

it('uses only chat.moderate for deletion, ban and unban authorization', function () {
    $policy = new ChatMessagePolicy;
    $target = Mockery::mock(User::class)->makePartial();
    $message = Mockery::mock(ChatMessage::class)->makePartial();

    $moderator = Mockery::mock(User::class)->makePartial();
    $moderator->shouldReceive('can')->with('chat.moderate')->andReturn(true);

    expect($policy->delete($moderator, $message))->toBeTrue()
        ->and($policy->ban($moderator, $target))->toBeTrue()
        ->and($policy->unban($moderator, $target))->toBeTrue();

    $plainUser = Mockery::mock(User::class)->makePartial();
    $plainUser->shouldReceive('can')->with('chat.moderate')->andReturn(false);

    expect($policy->delete($plainUser, $message))->toBeFalse()
        ->and($policy->ban($plainUser, $target))->toBeFalse()
        ->and($policy->unban($plainUser, $target))->toBeFalse();
});
