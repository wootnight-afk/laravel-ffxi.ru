<?php

use App\Enums\UserStatus;
use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Models\UserActiveCharacter;
use App\Services\CharacterContext;
use App\Services\GameCharacterSaver;

/**
 * Deletion of an active character (STAGE-2 §8, §9).
 *
 * The pointer must never outlive the character it names. Soft delete is handled
 * inside the delete transaction of GameCharacterSaver, so a failure rolls back
 * both `deleted_at` and the pointer.
 */
beforeEach(function () {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'status' => UserStatus::Active,
    ]);
    $this->user->assignRole('user');

    $this->server = GameServer::factory()->create([
        'name' => 'Asura',
        'slug' => 'asura',
        'is_active' => true,
    ]);

    $this->character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'name' => 'Junxi',
    ]);

    UserActiveCharacter::query()->create([
        'user_id' => $this->user->id,
        'character_id' => $this->character->id,
    ]);
});

it('drops the pointer together with a soft-deleted character', function () {
    app(GameCharacterSaver::class)->delete($this->user, $this->character);

    expect(UserActiveCharacter::query()->count())->toBe(0)
        ->and(GameCharacter::withTrashed()->find($this->character->id)->trashed())->toBeTrue();
});

it('rolls back the deletion when clearing the pointer fails', function () {
    $context = $this->mock(CharacterContext::class);
    $context->shouldReceive('forgetCharacter')->andThrow(new RuntimeException('boom'));

    expect(fn () => app(GameCharacterSaver::class)->delete($this->user, $this->character))
        ->toThrow(RuntimeException::class);

    expect(GameCharacter::withTrashed()->find($this->character->id)->deleted_at)->toBeNull()
        ->and(UserActiveCharacter::query()->count())->toBe(1);
});

it('removes the pointer with a hard delete through the cascade', function () {
    $this->character->forceDelete();

    expect(UserActiveCharacter::query()->count())->toBe(0);
});

it('does not reactivate a restored character', function () {
    $this->character->delete();

    expect(UserActiveCharacter::query()->count())->toBe(0);

    $this->character->restore();

    expect(UserActiveCharacter::query()->count())->toBe(0)
        ->and(app(CharacterContext::class)->for($this->user->refresh()))->toBeNull();
});
