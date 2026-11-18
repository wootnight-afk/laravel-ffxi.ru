<?php

use App\Enums\UserStatus;
use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Models\UserActiveCharacter;

/**
 * Active-character controls on the existing cabinet pages (STAGE-2 §7).
 *
 * The cabinet keeps a single My Characters section: these tests only check the
 * marker and the switch button on the pages that already exist.
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

    $this->active = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'name' => 'Junxi',
    ]);
    $this->inactive = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'name' => 'Rikku',
    ]);

    UserActiveCharacter::query()->create([
        'user_id' => $this->user->id,
        'character_id' => $this->active->id,
    ]);
});

it('marks the active character in the list and on its page', function () {
    $this->actingAs($this->user)
        ->get(route('cabinet.characters.index'))
        ->assertOk()
        ->assertSee(__('site.cabinet.characters.active_badge'))
        ->assertSee(__('site.cabinet.characters.make_active'));

    $this->actingAs($this->user)
        ->get(route('cabinet.characters.show', $this->active))
        ->assertOk()
        ->assertSee(__('site.cabinet.characters.active_badge'))
        ->assertDontSee(__('site.cabinet.characters.make_active'));

    $this->actingAs($this->user)
        ->get(route('cabinet.characters.show', $this->inactive))
        ->assertOk()
        ->assertSee(__('site.cabinet.characters.make_active'));
});

it('switches the active character from the cabinet', function () {
    $this->actingAs($this->user)
        ->from(route('cabinet.characters.index'))
        ->post(route('players.active-character.store'), ['character_id' => $this->inactive->id])
        ->assertRedirect(route('cabinet.characters.index'));

    expect((int) UserActiveCharacter::query()->whereKey($this->user->id)->value('character_id'))
        ->toBe((int) $this->inactive->id);
});

it('does not create a second characters section', function () {
    $this->actingAs($this->user)
        ->get(route('cabinet.characters.index'))
        ->assertOk()
        ->assertSee(route('cabinet.characters.index'), false);

    // The legacy alias still points at the same cabinet page.
    $this->actingAs($this->user)
        ->get(route('players.characters.index'))
        ->assertRedirect(route('cabinet.characters.index'));
});
