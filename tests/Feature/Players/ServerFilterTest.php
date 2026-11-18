<?php

use App\Enums\UserStatus;
use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Models\UserActiveCharacter;

/**
 * Game-server filter of the directory (STAGE-2 §6.1, §7, §10).
 *
 * The filter is a viewing preference stored in the session; the active
 * character is stored in the database. Neither reads or writes the other, in
 * either direction.
 */
beforeEach(function () {
    $this->viewer = User::factory()->create([
        'email_verified_at' => now(),
        'name' => 'Viewer Account',
        'status' => UserStatus::Active,
    ]);
    $this->viewer->assignRole('user');

    $this->asura = GameServer::factory()->create([
        'name' => 'Asura',
        'slug' => 'asura',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $this->bahamut = GameServer::factory()->create([
        'name' => 'Bahamut',
        'slug' => 'bahamut',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this->alpha = User::factory()->create(['name' => 'Alphaplayer', 'status' => 'active']);
    GameCharacter::factory()->create([
        'user_id' => $this->alpha->id,
        'game_server_id' => $this->asura->id,
        'name' => 'Alphahero',
        'is_public' => true,
    ]);

    $this->beta = User::factory()->create(['name' => 'Betaplayer', 'status' => 'active']);
    GameCharacter::factory()->hidden()->create([
        'user_id' => $this->beta->id,
        'game_server_id' => $this->asura->id,
        'name' => 'Betahero',
    ]);

    $this->gamma = User::factory()->create(['name' => 'Gammaplayer', 'status' => 'active']);
    GameCharacter::factory()->create([
        'user_id' => $this->gamma->id,
        'game_server_id' => $this->bahamut->id,
        'name' => 'Gammahero',
        'is_public' => true,
    ]);
});

it('stores the chosen server in the session', function () {
    $this->actingAs($this->viewer)
        ->post(route('players.server-filter.update'), ['server' => 'asura'])
        ->assertRedirect();

    expect(session('players.server_filter'))->toBe('asura');
});

it('clears the filter with an empty value', function () {
    $this->actingAs($this->viewer)
        ->withSession(['players.server_filter' => 'asura'])
        ->post(route('players.server-filter.update'), ['server' => ''])
        ->assertRedirect();

    expect(session('players.server_filter'))->toBeNull();
});

it('rejects an unknown server slug and keeps the previous value', function () {
    $this->actingAs($this->viewer)
        ->withSession(['players.server_filter' => 'asura'])
        ->post(route('players.server-filter.update'), ['server' => 'nonexistent'])
        ->assertSessionHasErrors('server');

    expect(session('players.server_filter'))->toBe('asura');
});

it('filters the directory by public characters of the chosen server', function () {
    $this->actingAs($this->viewer)
        ->get(route('players.directory'))
        ->assertOk()
        ->assertSee('Alphaplayer')
        ->assertSee('Betaplayer')
        ->assertSee('Gammaplayer');

    $this->actingAs($this->viewer)
        ->withSession(['players.server_filter' => 'asura'])
        ->get(route('players.directory'))
        ->assertOk()
        ->assertSee('Alphaplayer')
        ->assertDontSee('Betaplayer')
        ->assertDontSee('Gammaplayer');
});

it('ignores a filter that points at a deactivated server', function () {
    $this->asura->forceFill(['is_active' => false])->save();

    $this->actingAs($this->viewer)
        ->withSession(['players.server_filter' => 'asura'])
        ->get(route('players.directory'))
        ->assertOk()
        ->assertSee('Gammaplayer');

    // A GET request never rewrites the stored preference.
    expect(session('players.server_filter'))->toBe('asura');
});

it('does not change the active character', function () {
    $character = GameCharacter::factory()->create([
        'user_id' => $this->viewer->id,
        'game_server_id' => $this->bahamut->id,
        'name' => 'Viewerchar',
    ]);

    $this->actingAs($this->viewer)
        ->post(route('players.active-character.store'), ['character_id' => $character->id]);

    $this->actingAs($this->viewer)
        ->post(route('players.server-filter.update'), ['server' => 'asura'])
        ->assertRedirect();

    expect((int) UserActiveCharacter::query()->whereKey($this->viewer->id)->value('character_id'))
        ->toBe((int) $character->id);
});

it('is not changed by switching the active character', function () {
    $first = GameCharacter::factory()->create([
        'user_id' => $this->viewer->id,
        'game_server_id' => $this->asura->id,
        'name' => 'Firstchar',
    ]);
    $second = GameCharacter::factory()->create([
        'user_id' => $this->viewer->id,
        'game_server_id' => $this->bahamut->id,
        'name' => 'Secondchar',
    ]);

    $this->actingAs($this->viewer)
        ->withSession(['players.server_filter' => 'asura'])
        ->post(route('players.active-character.store'), ['character_id' => $first->id]);

    $this->actingAs($this->viewer)
        ->post(route('players.active-character.store'), ['character_id' => $second->id])
        ->assertRedirect();

    // The directory still shows the Asura listing, so the filter survived.
    $this->actingAs($this->viewer)
        ->get(route('players.directory'))
        ->assertOk()
        ->assertSee('Alphaplayer')
        ->assertDontSee('Gammaplayer');
});

it('reports the mismatch between the filter and the active character', function () {
    $character = GameCharacter::factory()->create([
        'user_id' => $this->viewer->id,
        'game_server_id' => $this->bahamut->id,
        'name' => 'Viewerchar',
    ]);

    $this->actingAs($this->viewer)
        ->post(route('players.active-character.store'), ['character_id' => $character->id]);

    $this->actingAs($this->viewer)
        ->withSession(['players.server_filter' => 'asura'])
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee(__('site.players.server_filter.mismatch'));
});
