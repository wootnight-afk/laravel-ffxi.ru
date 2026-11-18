<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Services\GameCharacterSaver;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Regression tests for STAGE-1 stabilisation: a partial update must never wipe
 * data the caller did not mention (jobs, legend, race, main job, publication).
 */
beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
    $this->server = GameServer::factory()->create();
});

it('keeps jobs and game data when the update does not mention them', function () {
    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'race' => 'hume',
        'main_job' => 'PLD',
        'legend' => 'Старая легенда',
        'is_public' => true,
    ]);

    $character->jobs()->create(['job' => 'PLD', 'level' => 75]);

    expect($character->jobs()->count())->toBe(1);

    $legendHtml = $character->fresh()->legend_html;

    app(GameCharacterSaver::class)->update($this->user, $character, [
        'name' => 'Renamed',
        'game_server_id' => $this->server->id,
    ]);

    $character->refresh();

    expect($character->name)->toBe('Renamed')
        ->and($character->jobs()->count())->toBe(1)
        ->and($character->jobs()->first()->level)->toBe(75)
        ->and($character->race?->value)->toBe('hume')
        ->and($character->main_job)->toBe('PLD')
        ->and($character->legend)->toBe('Старая легенда')
        ->and($character->legend_html)->toBe($legendHtml)
        ->and($character->is_public)->toBeTrue();
});

it('clears publication when the form sends zero', function () {
    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'is_public' => true,
    ]);

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => $character->name,
            'game_server_id' => $character->game_server_id,
            'is_public' => '0',
        ])
        ->assertRedirect(route('cabinet.characters.show', $character));

    expect($character->fresh()->is_public)->toBeFalse();
});

it('clears job levels when jobs are explicitly empty', function () {
    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
    ]);

    $character->jobs()->create(['job' => 'WHM', 'level' => 40]);

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => $character->name,
            'game_server_id' => $character->game_server_id,
            'jobs' => [],
        ])
        ->assertRedirect(route('cabinet.characters.show', $character));

    expect($character->jobs()->count())->toBe(0);
});
