<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Services\LegendLocaleBackfill;
use App\Support\LegendLanguage;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->server = GameServer::factory()->create();
});

function backfillCharacter(User $user, GameServer $server, array $attributes): GameCharacter
{
    return GameCharacter::factory()->create(array_merge([
        'user_id' => $user->getKey(),
        'game_server_id' => $server->getKey(),
    ], $attributes));
}

it('reports how the existing legends would be split', function () {
    backfillCharacter($this->user, $this->server, ['name' => 'RuChar', 'legend' => 'Русская легенда о герое']);
    backfillCharacter($this->user, $this->server, ['name' => 'EnChar', 'legend' => 'An english legend about the hero']);
    backfillCharacter($this->user, $this->server, ['name' => 'MixedChar', 'legend' => 'Привет мир hello world']);

    $report = app(LegendLocaleBackfill::class)->report();

    expect($report['with_legend'])->toBe(3)
        ->and($report[LegendLanguage::RU])->toBe(1)
        ->and($report[LegendLanguage::EN])->toBe(1)
        ->and($report[LegendLanguage::AMBIGUOUS])->toBe(1)
        ->and($report['ambiguous_list'])->toHaveCount(1)
        ->and($report['ambiguous_list'][0]['name'])->toBe('MixedChar');
});

it('copies the legends into the localized columns and keeps the source', function () {
    $ru = backfillCharacter($this->user, $this->server, ['name' => 'RuChar', 'legend' => 'Русская легенда о герое']);
    $en = backfillCharacter($this->user, $this->server, ['name' => 'EnChar', 'legend' => 'An english legend about the hero']);
    $mixed = backfillCharacter($this->user, $this->server, ['name' => 'MixedChar', 'legend' => 'Привет мир hello world']);

    $summary = app(LegendLocaleBackfill::class)->apply();

    expect($summary[LegendLanguage::RU])->toBe(1)
        ->and($summary[LegendLanguage::EN])->toBe(1)
        ->and($summary[LegendLanguage::AMBIGUOUS])->toBe(1);

    expect($ru->fresh()->legend_ru)->toBe('Русская легенда о герое')
        ->and($ru->fresh()->legend_ru_html)->toContain('Русская легенда')
        ->and($en->fresh()->legend_en)->toBe('An english legend about the hero')
        ->and($en->fresh()->legend_en_html)->toContain('english legend')
        ->and($mixed->fresh()->legend_ru)->toBe('Привет мир hello world')
        ->and($mixed->fresh()->legend_en)->toBeNull();

    // The legacy column is never modified: the text is copied, not moved.
    expect($ru->fresh()->legend)->toBe('Русская легенда о герое')
        ->and($en->fresh()->legend)->toBe('An english legend about the hero')
        ->and($mixed->fresh()->legend)->toBe('Привет мир hello world');
});

it('is safe to run twice', function () {
    $character = backfillCharacter($this->user, $this->server, ['name' => 'RuChar', 'legend' => 'Русская легенда о герое']);

    app(LegendLocaleBackfill::class)->apply();
    $second = app(LegendLocaleBackfill::class)->apply();

    expect($second['skipped'])->toBeGreaterThan(0)
        ->and($character->fresh()->legend_ru)->toBe('Русская легенда о герое');
});
