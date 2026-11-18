<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
});

function profilePayload(array $overrides = []): array
{
    return array_merge([
        'race' => 'hume',
        'main_job' => 'PLD',
    ], $overrides);
}

it('stores the two profile descriptions independently', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.profile.update'), profilePayload([
            'legend_ru' => '**Русское** описание',
            'legend_en' => '**English** description',
        ]))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'profile']));

    $fresh = $this->user->fresh();

    expect($fresh->legend_ru)->toBe('**Русское** описание')
        ->and($fresh->legend_en)->toBe('**English** description')
        ->and($fresh->legend_ru_html)->toContain('<strong>Русское</strong>')
        ->and($fresh->legend_en_html)->toContain('<strong>English</strong>');
});

it('edits one description without touching the other', function () {
    $this->user->forceFill([
        'legend_ru' => 'Русский текст',
        'legend_ru_html' => '<p>Русский текст</p>',
    ])->save();

    $this->actingAs($this->user)
        ->post(route('cabinet.profile.update'), profilePayload([
            'legend_en' => 'English only',
        ]))
        ->assertRedirect();

    $fresh = $this->user->fresh();

    expect($fresh->legend_ru)->toBe('Русский текст')
        ->and($fresh->legend_ru_html)->toBe('<p>Русский текст</p>')
        ->and($fresh->legend_en)->toBe('English only')
        ->and($fresh->legend_en_html)->toContain('English only');
});

it('shows only the description of the current locale', function () {
    $this->user->forceFill([
        'legend_ru' => 'Только русский',
        'legend_ru_html' => '<p>Только русский</p>',
        'legend_en' => 'Only english',
        'legend_en_html' => '<p>Only english</p>',
    ])->save();

    $this->actingAs($this->user)->withSession(['locale' => 'ru'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee('<p>Только русский</p>', false)
        ->assertDontSee('<p>Only english</p>', false);

    $this->actingAs($this->user)->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee('<p>Only english</p>', false)
        ->assertDontSee('<p>Только русский</p>', false);
});

it('does not substitute the other language when a version is empty', function () {
    $this->user->forceFill([
        'legend_en' => 'Only english',
        'legend_en_html' => '<p>Only english</p>',
    ])->save();

    $this->actingAs($this->user)->withSession(['locale' => 'ru'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee(__('site.cabinet.profile.legend_block_empty', [], 'ru'))
        ->assertDontSee('<p>Only english</p>', false);
});

it('renders markdown and strips unsafe html per version', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.profile.update'), profilePayload([
            'legend_ru' => "**Жирный**\n\n<script>alert(1)</script>",
            'legend_en' => '*Italic* [link](javascript:alert(1))',
        ]))
        ->assertRedirect();

    $fresh = $this->user->fresh();

    expect($fresh->legend_ru_html)->toContain('<strong>Жирный</strong>')
        ->and($fresh->legend_ru_html)->not->toContain('<script')
        ->and($fresh->legend_en_html)->toContain('<em>Italic</em>')
        ->and($fresh->legend_en_html)->not->toContain('javascript:');
});

it('keeps both descriptions when the update does not mention them', function () {
    $this->user->forceFill([
        'legend_ru' => 'Русский',
        'legend_ru_html' => '<p>Русский</p>',
        'legend_en' => 'English',
        'legend_en_html' => '<p>English</p>',
    ])->save();

    $this->actingAs($this->user)
        ->post(route('cabinet.profile.update'), profilePayload(['phone' => '+10000000000']))
        ->assertRedirect();

    $fresh = $this->user->fresh();

    expect($fresh->legend_ru)->toBe('Русский')
        ->and($fresh->legend_en)->toBe('English')
        ->and($fresh->legend_ru_html)->toBe('<p>Русский</p>')
        ->and($fresh->legend_en_html)->toBe('<p>English</p>');
});

it('shows only the edit field of the current locale', function () {
    $this->user->forceFill([
        'legend_ru' => 'Русский ввод',
        'legend_en' => 'English input',
    ])->save();

    $this->actingAs($this->user)->withSession(['locale' => 'ru'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee('name="legend_ru"', false)
        ->assertDontSee('name="legend_en"', false)
        ->assertSee('Русский ввод')
        ->assertDontSee('English input');

    $this->actingAs($this->user)->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee('name="legend_en"', false)
        ->assertDontSee('name="legend_ru"', false)
        ->assertSee('English input')
        ->assertDontSee('Русский ввод');
});

it('keeps the hidden version when the visible field is saved', function () {
    $this->user->forceFill([
        'legend_ru' => 'Русский',
        'legend_ru_html' => '<p>Русский</p>',
        'legend_en' => 'English',
        'legend_en_html' => '<p>English</p>',
    ])->save();

    $this->actingAs($this->user)->withSession(['locale' => 'en'])
        ->post(route('cabinet.profile.update'), profilePayload(['legend_en' => 'English updated']))
        ->assertRedirect();

    $fresh = $this->user->fresh();

    expect($fresh->legend_ru)->toBe('Русский')
        ->and($fresh->legend_ru_html)->toBe('<p>Русский</p>')
        ->and($fresh->legend_en)->toBe('English updated')
        ->and($fresh->legend_en_html)->toContain('English updated');
});

it('validates the length of each version', function () {
    $this->actingAs($this->user)
        ->post(route('cabinet.profile.update'), profilePayload([
            'legend_ru' => str_repeat('а', 4001),
        ]))
        ->assertSessionHasErrors(['legend_ru']);

    expect($this->user->fresh()->legend_ru)->toBeNull();
});
