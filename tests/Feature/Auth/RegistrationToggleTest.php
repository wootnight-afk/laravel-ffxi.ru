<?php

use App\Services\SettingsRepository;

beforeEach(function () {
    // Убеждаемся, что registration_open = false (default).
    app(SettingsRepository::class)->set('registration_open', false);
});

it('shows closed registration page on GET', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertSee('Регистрация временно приостановлена');
    $response->assertDontSee('name="password"', false);
});

it('rejects POST when registration is closed', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'NewPlayer',
        'email' => 'new@example.com',
        'password' => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
        'pd_consent' => '1',
    ]);

    $response->assertForbidden();
});

it('allows login when registration is closed', function () {
    $response = $this->get(route('login'));
    $response->assertOk();

    // POST с неверными данными — получаем валидацию, но не 403.
    $response = $this->post(route('login.post'), [
        'email' => 'nobody@example.com',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors('email');
});

it('opens registration when toggle is on', function () {
    app(SettingsRepository::class)->set('registration_open', true);

    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertSee('name="password"', false);
    $response->assertDontSee('Регистрация временно приостановлена');
});
