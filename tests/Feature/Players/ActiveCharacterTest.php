<?php

use App\Enums\UserStatus;
use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Models\UserActiveCharacter;
use Illuminate\Support\Facades\Auth;

/**
 * Active character context (STAGE-2 §5, §10).
 *
 * The pointer lives in the database, so the choice survives page transitions
 * and a repeated login; the switching endpoint is POST/DELETE only and never
 * confirms the existence of a foreign character.
 */
beforeEach(function () {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'name' => 'Denso',
        'status' => UserStatus::Active,
    ]);
    $this->user->assignRole('user');

    $this->serverA = GameServer::factory()->create([
        'name' => 'Asura',
        'slug' => 'asura',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $this->serverB = GameServer::factory()->create([
        'name' => 'Bahamut',
        'slug' => 'bahamut',
        'sort_order' => 2,
        'is_active' => true,
    ]);
});

function ownCharacter(User $user, GameServer $server, string $name): GameCharacter
{
    return GameCharacter::factory()->create([
        'user_id' => $user->id,
        'game_server_id' => $server->id,
        'name' => $name,
    ]);
}

it('starts with no active character', function () {
    $this->actingAs($this->user)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee(__('site.players.context.not_selected'));
});

it('stores the chosen character and shows it in the players panel', function () {
    $character = ownCharacter($this->user, $this->serverA, 'Junxi');

    $this->actingAs($this->user)
        ->post(route('players.active-character.store'), ['character_id' => $character->id])
        ->assertRedirect();

    expect((int) UserActiveCharacter::query()->whereKey($this->user->id)->value('character_id'))
        ->toBe((int) $character->id);

    $this->actingAs($this->user)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('Junxi');
});

it('keeps the choice between requests and after a repeated login', function () {
    $character = ownCharacter($this->user, $this->serverA, 'Junxi');

    $this->actingAs($this->user)
        ->post(route('players.active-character.store'), ['character_id' => $character->id]);

    $this->actingAs($this->user)
        ->get(route('players.directory'))
        ->assertOk()
        ->assertSee('Junxi');

    Auth::logout();
    $this->flushSession();

    $this->actingAs($this->user)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('Junxi');
});

it('replaces the previous choice instead of adding a second row', function () {
    $first = ownCharacter($this->user, $this->serverA, 'Junxi');
    $second = ownCharacter($this->user, $this->serverB, 'Rikku');

    $this->actingAs($this->user)
        ->post(route('players.active-character.store'), ['character_id' => $first->id]);
    $this->actingAs($this->user)
        ->post(route('players.active-character.store'), ['character_id' => $second->id]);

    expect(UserActiveCharacter::query()->count())->toBe(1)
        ->and((int) UserActiveCharacter::query()->whereKey($this->user->id)->value('character_id'))
        ->toBe((int) $second->id);
});

it('rejects a character of another account without confirming it exists', function () {
    $other = User::factory()->create([
        'email_verified_at' => now(),
        'status' => UserStatus::Active,
    ]);
    $other->assignRole('user');
    $foreign = ownCharacter($other, $this->serverA, 'Foreign');

    $this->actingAs($this->user)
        ->post(route('players.active-character.store'), ['character_id' => $foreign->id])
        ->assertNotFound();

    expect(UserActiveCharacter::query()->count())->toBe(0);
});

it('rejects a missing and a soft-deleted character with the same status', function () {
    $deleted = ownCharacter($this->user, $this->serverA, 'Gone');
    $deleted->delete();

    $this->actingAs($this->user)
        ->post(route('players.active-character.store'), ['character_id' => 999999])
        ->assertNotFound();

    $this->actingAs($this->user)
        ->post(route('players.active-character.store'), ['character_id' => $deleted->id])
        ->assertNotFound();

    expect(UserActiveCharacter::query()->count())->toBe(0);
});

it('validates the payload of the switch request', function () {
    $this->actingAs($this->user)
        ->postJson(route('players.active-character.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('character_id');

    $this->actingAs($this->user)
        ->postJson(route('players.active-character.store'), ['character_id' => 'not-a-number'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('character_id');
});

it('denies switching for an account that cannot hold a character', function () {
    $character = ownCharacter($this->user, $this->serverA, 'Junxi');
    $this->user->forceFill(['status' => UserStatus::Suspended])->save();

    $this->actingAs($this->user->refresh())
        ->post(route('players.active-character.store'), ['character_id' => $character->id])
        ->assertForbidden();

    expect(UserActiveCharacter::query()->count())->toBe(0);
});

it('clears the choice with a delete request', function () {
    $character = ownCharacter($this->user, $this->serverA, 'Junxi');

    $this->actingAs($this->user)
        ->post(route('players.active-character.store'), ['character_id' => $character->id]);
    $this->actingAs($this->user)
        ->delete(route('players.active-character.destroy'))
        ->assertRedirect();

    expect(UserActiveCharacter::query()->count())->toBe(0);

    $this->actingAs($this->user)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee(__('site.players.context.not_selected'));
});

it('never changes the pointer on a get request', function () {
    $character = ownCharacter($this->user, $this->serverA, 'Junxi');

    UserActiveCharacter::query()->create([
        'user_id' => $this->user->id,
        'character_id' => $character->id,
    ]);

    // The character becomes unavailable behind the application's back: the
    // pointer stays, the context is empty, and no GET repairs the database.
    GameCharacter::query()->whereKey($character->id)->update(['deleted_at' => now()]);

    $this->actingAs($this->user)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee(__('site.players.context.not_selected'));

    expect(UserActiveCharacter::query()->whereKey($this->user->id)->exists())->toBeTrue();
});

it('shows the switcher on the players pages only', function () {
    $this->actingAs($this->user)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('players-context', false);

    $this->actingAs($this->user)
        ->get(route('players.directory'))
        ->assertOk()
        ->assertSee('players-context', false);

    // /players/{user} does not use the players shell, so the block is absent.
    $this->actingAs($this->user)
        ->get(route('players.show', $this->user))
        ->assertOk()
        ->assertDontSee('players-context', false);
});

it('renders the switcher in english', function () {
    ownCharacter($this->user, $this->serverA, 'Junxi');

    $this->actingAs($this->user)
        ->withSession(['locale' => 'en'])
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee(__('site.players.context.label', [], 'en'))
        ->assertSee(__('site.players.server_filter.all', [], 'en'));
});
