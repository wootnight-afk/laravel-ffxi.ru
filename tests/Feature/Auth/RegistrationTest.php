<?php

use App\Models\User;
use App\Services\SettingsRepository;

beforeEach(function () {
    app(SettingsRepository::class)->set('registration_open', true);
});

it('rejects duplicate nickname case-insensitively', function () {
    User::factory()->create(['name' => 'Jumxi']);

    $response = $this->post(route('register.store'), [
        'name' => 'jumxi',
        'email' => 'new@example.com',
        'password' => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
        'pd_consent' => '1',
    ]);

    $response->assertSessionHasErrors('name');
    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
});

it('rejects duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'NewPlayer',
        'email' => 'taken@example.com',
        'password' => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
        'pd_consent' => '1',
    ]);

    $response->assertSessionHasErrors('email');
});

it('requires pd consent', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'NewPlayer',
        'email' => 'new@example.com',
        'password' => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
    ]);

    $response->assertSessionHasErrors('pd_consent');
});

it('registers a user and assigns the user role', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'NewPlayer',
        'email' => 'new@example.com',
        'password' => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
        'pd_consent' => '1',
    ]);

    $response->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('user'))->toBeTrue();
    expect($user->pd_consent_at)->not->toBeNull();
    expect($user->pd_policy_version)->toBe('draft');
    expect($user->status->value)->toBe('active');
});

it('suggests available nicknames when the nickname is taken', function () {
    User::factory()->create(['name' => 'Bugor']);

    $response = $this->postJson(route('api.nickname.check'), [
        'name' => 'Bugor',
    ]);

    $response->assertOk()
        ->assertJson([
            'available' => false,
            'reason' => 'taken',
        ]);

    $suggestions = $response->json('suggestions');
    expect($suggestions)->toBeArray()->toHaveCount(3);

    foreach ($suggestions as $nickname) {
        expect(User::where('name', $nickname)->exists())->toBeFalse();
    }
});

it('reports available nickname', function () {
    $response = $this->postJson(route('api.nickname.check'), [
        'name' => 'FreshNickname',
    ]);

    $response->assertOk()->assertJson([
        'available' => true,
        'reason' => null,
    ]);
});

it('rejects blacklisted nickname', function () {
    $response = $this->postJson(route('api.nickname.check'), [
        'name' => 'admin',
    ]);

    $response->assertOk()->assertJson([
        'available' => false,
        'reason' => 'blacklisted',
    ]);
});
