<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Services\ContentRenderer;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Rendering checks for the cabinet character pages: the list with several
 * characters, the long name limit and the character page in both locales.
 */
beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
    $this->server = GameServer::factory()->create(['name' => 'Asura', 'sort_order' => 1]);
});

it('renders the list with several characters sorted by server', function () {
    GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'name' => str_repeat('A', 24),
    ]);
    GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'name' => 'Denso',
        'legend' => 'Легенда',
    ]);

    $response = $this->actingAs($this->user)->get(route('cabinet.characters.index'));

    $response->assertOk()
        ->assertSee(str_repeat('A', 24))
        ->assertSee('Denso')
        ->assertSee('Asura');
});

it('renders the character page with jobs and legend in both locales', function () {
    $renderer = app(ContentRenderer::class);

    $character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'name' => 'Junxi',
        'race' => 'mithra',
        'main_job' => 'WHM',
        'legend_ru' => '**Живая легенда**',
        'legend_ru_html' => $renderer->render('**Живая легенда**'),
        'legend_en' => '**Living legend**',
        'legend_en_html' => $renderer->render('**Living legend**'),
    ]);

    $character->jobs()->create(['job' => 'WHM', 'level' => 75]);
    $character->jobs()->create(['job' => 'PLD', 'level' => 40]);

    foreach (['ru', 'en'] as $locale) {
        $response = $this->actingAs($this->user)
            ->withSession(['locale' => $locale])
            ->get(route('cabinet.characters.show', $character));

        $expectedLegend = $locale === 'ru'
            ? '<strong>Живая легенда</strong>'
            : '<strong>Living legend</strong>';

        $response->assertOk()
            ->assertSee('Junxi')
            ->assertSee('WHM')
            ->assertSee(__('site.cabinet.characters.fields.jobs', [], $locale))
            ->assertSee($expectedLegend, false);
    }
});

it('renders the empty state in both locales', function () {
    foreach (['ru', 'en'] as $locale) {
        $this->actingAs($this->user)
            ->withSession(['locale' => $locale])
            ->get(route('cabinet.characters.index'))
            ->assertOk()
            ->assertSee(__('site.cabinet.characters.empty', [], $locale));
    }
});
