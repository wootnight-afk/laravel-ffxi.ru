<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
});

it('redirects guests from the cabinet characters page to login', function () {
    $this->get(route('cabinet.characters.index'))->assertRedirect(route('login'));
});

it('shows an empty state when the account has no characters', function () {
    GameServer::factory()->create();

    $response = $this->actingAs($this->user)->get(route('cabinet.characters.index'));

    $response->assertOk();
    $response->assertSee(__('site.cabinet.characters.empty'));
});

it('shows the no-servers notice when the server list is empty', function () {
    $response = $this->actingAs($this->user)->get(route('cabinet.characters.index'));

    $response->assertOk();
    $response->assertSee(__('site.cabinet.characters.no_servers'));
});

it('creates a character for the authenticated account', function () {
    $server = GameServer::factory()->create();

    $response = $this->actingAs($this->user)->post(route('cabinet.characters.store'), [
        'name' => 'Bugor',
        'game_server_id' => $server->id,
        'is_public' => '1',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('game_characters', [
        'user_id' => $this->user->id,
        'game_server_id' => $server->id,
        'name' => 'Bugor',
        'is_public' => true,
    ]);
});

it('allows the same name on different servers', function () {
    $phoenix = GameServer::factory()->create(['name' => 'Phoenix', 'slug' => 'phoenix']);
    $asura = GameServer::factory()->create(['name' => 'Asura', 'slug' => 'asura']);

    $this->actingAs($this->user)->post(route('cabinet.characters.store'), [
        'name' => 'Bugor',
        'game_server_id' => $phoenix->id,
    ])->assertRedirect();

    $this->actingAs($this->user)->post(route('cabinet.characters.store'), [
        'name' => 'Bugor',
        'game_server_id' => $asura->id,
    ])->assertRedirect();

    expect($this->user->gameCharacters()->count())->toBe(2);
});

it('rejects a duplicate name on the same server regardless of case', function () {
    $server = GameServer::factory()->create();
    GameCharacter::factory()->create(['game_server_id' => $server->id, 'name' => 'Bugor']);

    $response = $this->actingAs($this->user)->post(route('cabinet.characters.store'), [
        'name' => 'bugor',
        'game_server_id' => $server->id,
    ]);

    $response->assertSessionHasErrors('name');
    expect(GameCharacter::withTrashed()->where('game_server_id', $server->id)->count())->toBe(1);
});

it('keeps the name of a soft-deleted character occupied', function () {
    $server = GameServer::factory()->create();
    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $server->id,
        'name' => 'Bugor',
    ]);

    $this->actingAs($this->user)
        ->delete(route('cabinet.characters.destroy', $character))
        ->assertRedirect();

    $this->assertSoftDeleted('game_characters', ['id' => $character->id]);

    $this->actingAs($this->user)->post(route('cabinet.characters.store'), [
        'name' => 'Bugor',
        'game_server_id' => $server->id,
    ])->assertSessionHasErrors('name');
});

it('rejects an inactive server on create', function () {
    $server = GameServer::factory()->inactive()->create();

    $this->actingAs($this->user)->post(route('cabinet.characters.store'), [
        'name' => 'Bugor',
        'game_server_id' => $server->id,
    ])->assertSessionHasErrors('game_server_id');
});

it('ignores a user_id supplied in the request', function () {
    $other = User::factory()->create(['email_verified_at' => now()]);
    $server = GameServer::factory()->create();

    $this->actingAs($this->user)->post(route('cabinet.characters.store'), [
        'name' => 'Bugor',
        'game_server_id' => $server->id,
        'user_id' => $other->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('game_characters', ['name' => 'Bugor', 'user_id' => $this->user->id]);
});

it('forbids viewing a character of another account', function () {
    $character = GameCharacter::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->user)
        ->get(route('cabinet.characters.show', $character))
        ->assertForbidden();
});

it('forbids editing and deleting a character of another account', function () {
    $character = GameCharacter::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => 'Hijacked',
            'game_server_id' => $character->game_server_id,
        ])
        ->assertForbidden();

    $this->actingAs($this->user)
        ->delete(route('cabinet.characters.destroy', $character))
        ->assertForbidden();

    expect($character->fresh()->name)->not->toBe('Hijacked');
});

it('returns 404 for a soft-deleted character', function () {
    $character = GameCharacter::factory()->create(['user_id' => $this->user->id]);
    $character->delete();

    $this->actingAs($this->user)
        ->get(route('cabinet.characters.show', $character))
        ->assertNotFound();
});

it('updates visibility of an own character', function () {
    $character = GameCharacter::factory()->create(['user_id' => $this->user->id, 'is_public' => true]);

    // Publication is cleared by an explicit value: the form always submits a
    // hidden "0" behind the checkbox, so a missing key never resets anything.
    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => $character->name,
            'game_server_id' => $character->game_server_id,
            'is_public' => '0',
        ])
        ->assertRedirect();

    expect($character->fresh()->is_public)->toBeFalse();
});

it('soft deletes an own character', function () {
    $character = GameCharacter::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->delete(route('cabinet.characters.destroy', $character))
        ->assertRedirect();

    $this->assertSoftDeleted('game_characters', ['id' => $character->id]);
});

it('forbids writing for an unverified account', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');
    $server = GameServer::factory()->create();

    $this->actingAs($user)->post(route('cabinet.characters.store'), [
        'name' => 'Bugor',
        'game_server_id' => $server->id,
    ])->assertRedirect(route('verification.notice'));

    expect(GameCharacter::query()->count())->toBe(0);
});

it('excludes hidden characters from the public scope', function () {
    GameCharacter::factory()->create(['user_id' => $this->user->id, 'is_public' => true, 'name' => 'Visible']);
    GameCharacter::factory()->hidden()->create(['user_id' => $this->user->id, 'name' => 'Hidden']);

    expect(GameCharacter::query()->public()->pluck('name'))
        ->toContain('Visible')
        ->not->toContain('Hidden');
});

it('keeps legacy race and main_job fields untouched', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'race' => 'Hume',
        'main_job' => 'PLD',
    ]);
    $user->assignRole('user');

    GameCharacter::factory()->create(['user_id' => $user->id]);

    $user->refresh();
    expect($user->race)->toBe('Hume')->and($user->main_job)->toBe('PLD');
});
