<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
    $this->server = GameServer::factory()->create();
});

function characterPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Bugor',
        'game_server_id' => $overrides['game_server_id'] ?? GameServer::query()->value('id'),
        'is_public' => '1',
        'race' => 'hume',
        'main_job' => 'PLD',
        'jobs' => ['PLD' => 75, 'WHM' => 40, 'WAR' => 30],
        'legend' => "**Живая легенда** Санд'Ории",
    ], $overrides);
}

it('stores race, main job, jobs and legend on create', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.characters.store'), characterPayload())
        ->assertRedirect();

    $character = GameCharacter::query()->firstOrFail();

    expect($character->race?->value)->toBe('hume')
        ->and($character->main_job)->toBe('PLD')
        ->and($character->jobs()->count())->toBe(3)
        ->and($character->jobs()->where('job', 'PLD')->value('level'))->toBe(75)
        ->and($character->legend)->toContain('Живая легенда')
        ->and($character->legend_html)->toContain('<strong>');
});

it('rejects a main job that is not listed among the jobs', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.characters.store'), characterPayload([
            'main_job' => 'BLM',
            'jobs' => ['PLD' => 75],
        ]))
        ->assertSessionHasErrors('main_job');

    expect(GameCharacter::query()->count())->toBe(0);
});

it('rejects a main job when no jobs are given', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.characters.store'), characterPayload([
            'main_job' => 'PLD',
            'jobs' => [],
        ]))
        ->assertSessionHasErrors('main_job');
});

it('rejects out of range levels and drops empty fields', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.characters.store'), characterPayload([
            'main_job' => null,
            'jobs' => ['PLD' => 0, 'WHM' => 100],
        ]))
        ->assertSessionHasErrors(['jobs.PLD', 'jobs.WHM']);

    expect(GameCharacter::query()->count())->toBe(0);

    $this->actingAs($this->user)
        ->post(route('cabinet.characters.store'), characterPayload([
            'main_job' => null,
            'jobs' => ['PLD' => 75, 'WHM' => ''],
        ]))
        ->assertRedirect();

    $character = GameCharacter::query()->firstOrFail();
    expect($character->jobs()->pluck('job')->map->value->all())->toBe(['PLD']);
});

it('keeps legend html derived and ignores the request value', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.characters.store'), characterPayload([
            'legend' => 'Простая **легенда**',
            'legend_html' => '<script>alert(1)</script>',
        ]))
        ->assertRedirect();

    $character = GameCharacter::query()->firstOrFail();

    expect($character->legend_html)->toContain('<strong>')
        ->and($character->legend_html)->not->toContain('script');
});

it('sanitises unsafe legend markup', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.characters.store'), characterPayload([
            'legend' => '<script>alert(1)</script>[ссылка](javascript:alert(2))',
        ]))
        ->assertRedirect();

    $html = GameCharacter::query()->firstOrFail()->legend_html;

    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('javascript:');
});

it('writes null html for an empty legend', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.characters.store'), characterPayload(['legend' => '']))
        ->assertRedirect();

    expect(GameCharacter::query()->firstOrFail()->legend_html)->toBeNull();
});

it('recomputes legend html only when the legend changed', function () {
    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Bugor',
        'legend' => 'Текст',
        'legend_html' => '<p>Текст</p>',
    ]);

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => 'Bugor',
            'game_server_id' => $character->game_server_id,
            'legend' => 'Текст',
        ])
        ->assertRedirect();

    expect($character->fresh()->legend_html)->toBe('<p>Текст</p>');

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => 'Bugor',
            'game_server_id' => $character->game_server_id,
            'legend' => 'Новый текст',
        ])
        ->assertRedirect();

    expect($character->fresh()->legend_html)->toContain('Новый текст');
});

it('shows the not specified caption for an empty race', function () {
    GameCharacter::factory()->create(['user_id' => $this->user->id, 'race' => null]);

    $this->actingAs($this->user)
        ->get(route('cabinet.characters.index'))
        ->assertOk()
        ->assertSee(__('site.cabinet.characters.race_none'));
});

it('allows saving a character that already lives on a deactivated server', function () {
    $inactive = GameServer::factory()->inactive()->create();
    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $inactive->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => $character->name,
            'game_server_id' => $inactive->id,
            'legend' => 'Правка',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($character->fresh()->legend)->toBe('Правка');
});

it('forbids switching to another inactive server', function () {
    $inactive = GameServer::factory()->inactive()->create();
    $otherInactive = GameServer::factory()->inactive()->create();
    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $inactive->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => $character->name,
            'game_server_id' => $otherInactive->id,
        ])
        ->assertSessionHasErrors('game_server_id');
});

it('does not let a substituted inactive server id pass validation', function () {
    $active = GameServer::factory()->create();
    $inactive = GameServer::factory()->inactive()->create();
    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $active->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $character), [
            'name' => $character->name,
            'game_server_id' => $inactive->id,
        ])
        ->assertSessionHasErrors('game_server_id');
});

it('does not touch the legacy account fields while saving a character', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'race' => 'Elvaan',
        'main_job' => 'WHM',
        'legend' => 'Старая легенда',
        'legend_html' => '<p>Старая легенда</p>',
    ]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->post(route('cabinet.characters.store'), characterPayload(['name' => 'Second']))
        ->assertRedirect();

    $user->refresh();
    expect($user->race)->toBe('Elvaan')
        ->and($user->main_job)->toBe('WHM')
        ->and($user->legend)->toBe('Старая легенда')
        ->and($user->legend_html)->toBe('<p>Старая легенда</p>');
});
