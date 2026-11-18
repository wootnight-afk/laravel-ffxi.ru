<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Services\GameCharacterSaver;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
    $this->server = GameServer::factory()->create();

    $this->character = GameCharacter::factory()->create([
        'user_id' => $this->user->id,
        'game_server_id' => $this->server->id,
        'name' => 'Legendary',
    ]);
});

function legendPayload(GameCharacter $character, array $overrides = []): array
{
    return array_merge([
        'name' => $character->name,
        'game_server_id' => $character->game_server_id,
    ], $overrides);
}

it('stores the two legends independently', function () {
    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $this->character), legendPayload($this->character, [
            'legend_ru' => '**Русская** легенда',
            'legend_en' => '**English** legend',
        ]))
        ->assertRedirect();

    $fresh = $this->character->fresh();

    expect($fresh->legend_ru)->toBe('**Русская** легенда')
        ->and($fresh->legend_en)->toBe('**English** legend')
        ->and($fresh->legend_ru_html)->toContain('<strong>Русская</strong>')
        ->and($fresh->legend_en_html)->toContain('<strong>English</strong>');
});

it('edits one version without touching the other', function () {
    $this->character->forceFill([
        'legend_ru' => 'Русский текст',
        'legend_ru_html' => '<p>Русский текст</p>',
    ])->save();

    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $this->character), legendPayload($this->character, [
            'legend_en' => 'English only',
        ]))
        ->assertRedirect();

    $fresh = $this->character->fresh();

    expect($fresh->legend_ru)->toBe('Русский текст')
        ->and($fresh->legend_ru_html)->toBe('<p>Русский текст</p>')
        ->and($fresh->legend_en)->toBe('English only')
        ->and($fresh->legend_en_html)->toContain('English only');
});

it('shows only the legend of the current locale', function () {
    $this->character->forceFill([
        'legend_ru' => 'Только русский',
        'legend_ru_html' => '<p>Только русский</p>',
        'legend_en' => 'Only english',
        'legend_en_html' => '<p>Only english</p>',
    ])->save();

    $this->actingAs($this->user)->withSession(['locale' => 'ru'])
        ->get(route('cabinet.characters.show', $this->character))
        ->assertOk()
        ->assertSee('<p>Только русский</p>', false)
        ->assertDontSee('<p>Only english</p>', false);

    $this->actingAs($this->user)->withSession(['locale' => 'en'])
        ->get(route('cabinet.characters.show', $this->character))
        ->assertOk()
        ->assertSee('<p>Only english</p>', false)
        ->assertDontSee('<p>Только русский</p>', false);
});

it('does not substitute the other language when the version is missing', function () {
    $this->character->forceFill([
        'legend_en' => 'Only english',
        'legend_en_html' => '<p>Only english</p>',
    ])->save();

    $this->actingAs($this->user)->withSession(['locale' => 'ru'])
        ->get(route('cabinet.characters.show', $this->character))
        ->assertOk()
        ->assertSee(__('site.cabinet.characters.legend.empty', [], 'ru'))
        ->assertDontSee('<p>Only english</p>', false);
});

it('renders markdown and strips unsafe html per version', function () {
    $this->actingAs($this->user)
        ->put(route('cabinet.characters.update', $this->character), legendPayload($this->character, [
            'legend_ru' => "**Жирный**\n\n<script>alert(1)</script>",
            'legend_en' => '*Italic* [link](javascript:alert(1))',
        ]))
        ->assertRedirect();

    $fresh = $this->character->fresh();

    expect($fresh->legend_ru_html)->toContain('<strong>Жирный</strong>')
        ->and($fresh->legend_ru_html)->not->toContain('<script')
        ->and($fresh->legend_en_html)->toContain('<em>Italic</em>')
        ->and($fresh->legend_en_html)->not->toContain('javascript:');
});

it('keeps both legends when a partial update does not mention them', function () {
    $this->character->forceFill([
        'legend_ru' => 'Русский',
        'legend_ru_html' => '<p>Русский</p>',
        'legend_en' => 'English',
        'legend_en_html' => '<p>English</p>',
    ])->save();

    app(GameCharacterSaver::class)->update($this->user, $this->character, [
        'name' => 'Renamed',
        'game_server_id' => $this->server->id,
    ]);

    $fresh = $this->character->fresh();

    expect($fresh->name)->toBe('Renamed')
        ->and($fresh->legend_ru)->toBe('Русский')
        ->and($fresh->legend_ru_html)->toBe('<p>Русский</p>')
        ->and($fresh->legend_en)->toBe('English')
        ->and($fresh->legend_en_html)->toBe('<p>English</p>');
});

it('shows only the edit field of the current locale', function () {
    $this->character->forceFill([
        'legend_ru' => 'Русский текст',
        'legend_en' => 'English text',
    ])->save();

    $this->actingAs($this->user)->withSession(['locale' => 'ru'])
        ->get(route('cabinet.characters.show', $this->character))
        ->assertOk()
        ->assertSee('name="legend_ru"', false)
        ->assertDontSee('name="legend_en"', false);

    $this->actingAs($this->user)->withSession(['locale' => 'en'])
        ->get(route('cabinet.characters.show', $this->character))
        ->assertOk()
        ->assertSee('name="legend_en"', false)
        ->assertDontSee('name="legend_ru"', false);
});

it('keeps the hidden legend when only the visible field is saved', function () {
    $this->character->forceFill([
        'legend_ru' => 'Русский',
        'legend_ru_html' => '<p>Русский</p>',
        'legend_en' => 'English',
        'legend_en_html' => '<p>English</p>',
    ])->save();

    $this->actingAs($this->user)->withSession(['locale' => 'en'])
        ->put(route('cabinet.characters.update', $this->character), legendPayload($this->character, [
            'legend_en' => 'English updated',
        ]))
        ->assertRedirect();

    $fresh = $this->character->fresh();

    expect($fresh->legend_ru)->toBe('Русский')
        ->and($fresh->legend_ru_html)->toBe('<p>Русский</p>')
        ->and($fresh->legend_en)->toBe('English updated')
        ->and($fresh->legend_en_html)->toContain('English updated');
});
