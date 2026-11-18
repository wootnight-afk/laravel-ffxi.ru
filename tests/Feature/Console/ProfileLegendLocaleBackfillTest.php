<?php

use App\Models\User;
use App\Services\ProfileLegendLocaleBackfill;
use App\Support\LegendLanguage;

function profileAccount(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'email_verified_at' => now(),
    ], $attributes));
}

it('reports how the existing profile descriptions would be split', function () {
    profileAccount(['name' => 'RuAccount', 'legend' => 'Русское описание профиля']);
    profileAccount(['name' => 'EnAccount', 'legend' => 'An english profile description']);
    profileAccount(['name' => 'MixedAccount', 'legend' => 'Привет мир hello world']);

    $report = app(ProfileLegendLocaleBackfill::class)->report();

    expect($report['with_legend'])->toBe(3)
        ->and($report[LegendLanguage::RU])->toBe(1)
        ->and($report[LegendLanguage::EN])->toBe(1)
        ->and($report[LegendLanguage::AMBIGUOUS])->toBe(1)
        ->and($report['ambiguous_list'])->toHaveCount(1);
});

it('copies the descriptions into the localized columns and keeps the source', function () {
    $ru = profileAccount(['name' => 'RuAccount', 'legend' => 'Русское описание профиля']);
    $en = profileAccount(['name' => 'EnAccount', 'legend' => 'An english profile description']);
    $mixed = profileAccount(['name' => 'MixedAccount', 'legend' => 'Привет мир hello world']);

    $summary = app(ProfileLegendLocaleBackfill::class)->apply();

    expect($summary[LegendLanguage::RU])->toBe(1)
        ->and($summary[LegendLanguage::EN])->toBe(1)
        ->and($summary[LegendLanguage::AMBIGUOUS])->toBe(1)
        ->and($summary['errors'])->toBe(0);

    expect($ru->fresh()->legend_ru)->toBe('Русское описание профиля')
        ->and($ru->fresh()->legend_ru_html)->toContain('Русское описание')
        ->and($en->fresh()->legend_en)->toBe('An english profile description')
        ->and($en->fresh()->legend_en_html)->toContain('english profile')
        ->and($mixed->fresh()->legend_ru)->toBe('Привет мир hello world')
        ->and($mixed->fresh()->legend_en)->toBeNull();

    // The source column is never modified.
    expect($ru->fresh()->legend)->toBe('Русское описание профиля')
        ->and($en->fresh()->legend)->toBe('An english profile description');
});

it('is safe to run twice', function () {
    $user = profileAccount(['legend' => 'Русское описание']);

    app(ProfileLegendLocaleBackfill::class)->apply();
    $second = app(ProfileLegendLocaleBackfill::class)->apply();

    expect($second['skipped'])->toBeGreaterThan(0)
        ->and($user->fresh()->legend_ru)->toBe('Русское описание');
});

it('runs through the console command with --profiles', function () {
    profileAccount(['legend' => 'Русское описание профиля']);

    $this->artisan('characters:legend-locale', ['--report' => true, '--profiles' => true])
        ->assertSuccessful();

    $this->artisan('characters:legend-locale', ['--apply' => true, '--profiles' => true])
        ->assertSuccessful();

    expect(User::query()->whereNotNull('legend_ru')->count())->toBe(1);
});
