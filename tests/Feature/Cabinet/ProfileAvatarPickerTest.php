<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
});

it('renders the localized file picker labels', function () {
    $this->actingAs($this->user)->withSession(['locale' => 'ru'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee('data-file-field', false)
        ->assertSee('Выберите файл')
        ->assertSee('Файл не выбран')
        ->assertDontSee('Choose file');

    $this->actingAs($this->user)->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee('Choose file')
        ->assertSee('No file chosen')
        ->assertDontSee('Выберите файл');
});

it('keeps the standard upload behaviour of the avatar field', function () {
    $response = $this->actingAs($this->user)->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk();

    $html = $response->getContent();

    // The native input stays in the form: required, named, image-only and
    // submitted as multipart — only its labels are localized.
    expect($html)->toContain('enctype="multipart/form-data"')
        ->and($html)->toContain('name="avatar"')
        ->and($html)->toContain('accept="image/jpeg,image/png,image/webp"')
        ->and($html)->toContain('data-submit-once');
});
