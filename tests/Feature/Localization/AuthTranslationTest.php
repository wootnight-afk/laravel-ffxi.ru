<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsRepository;

it('renders the login page in english', function () {
    $this->withSession(['locale' => 'en'])
        ->get(route('login'))
        ->assertOk()
        ->assertSee('Log in')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertSee('Forgot your password?')
        ->assertSee('Create an account')
        ->assertDontSee('Авторизация')
        ->assertDontSee('Запомнить меня');
});

it('renders the registration page in english', function () {
    app(SettingsRepository::class)->set('registration_open', true);

    $this->withSession(['locale' => 'en'])
        ->get(route('register'))
        ->assertOk()
        ->assertSee('Registration')
        ->assertSee('Nickname')
        ->assertSee('Repeat password')
        ->assertSee('Phone (optional)')
        ->assertSee('I agree to the processing of personal data')
        ->assertSee('Register')
        ->assertDontSee('Регистрация')
        ->assertDontSee('Пароль ещё раз');
});

it('renders the password recovery pages in english', function () {
    $this->withSession(['locale' => 'en'])
        ->get(route('password.request'))
        ->assertOk()
        ->assertSee('Password recovery')
        ->assertSee('We will send a password reset link')
        ->assertSee('Send link')
        ->assertDontSee('Восстановление пароля');

    $this->withSession(['locale' => 'en'])
        ->get(route('password.reset', ['token' => 'test-token', 'email' => 'user@example.com']))
        ->assertOk()
        ->assertSee('Reset password')
        ->assertSee('New password')
        ->assertSee('Repeat new password')
        ->assertDontSee('Сброс пароля');
});

it('renders the email verification page in english', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Verify your email')
        ->assertSee('Resend email')
        ->assertSee('Log out')
        ->assertDontSee('Подтвердите ваш email');
});

it('renders the closed registration page in english', function () {
    app(SettingsRepository::class)->set('registration_open', false);

    $this->withSession(['locale' => 'en'])
        ->get(route('register'))
        ->assertOk()
        ->assertSee('Registration is temporarily closed')
        ->assertDontSee('Регистрация временно приостановлена');
});

it('renders authentication errors in the active locale', function () {
    $this->withSession(['locale' => 'en'])
        ->post(route('login.post'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors(['email' => 'Invalid email or password.']);

    $this->withSession(['locale' => 'ru'])
        ->post(route('login.post'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors(['email' => 'Неверный email или пароль.']);
});

it('renders registration validation messages in the active locale', function () {
    app(SettingsRepository::class)->set('registration_open', true);

    $this->withSession(['locale' => 'en'])
        ->post(route('register.store'), [])
        ->assertSessionHasErrors([
            'name' => 'Enter a nickname.',
            'email' => 'Enter an email address.',
            'password' => 'Enter a password.',
            'pd_consent' => 'Consent to personal data processing is required.',
        ]);
});

it('renders password reset status messages in the active locale', function () {
    $this->withSession(['locale' => 'en'])
        ->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHas('status', 'If such an account exists, a link has been sent.');

    $this->withSession(['locale' => 'ru'])
        ->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHas('status', 'Если такой аккаунт существует, письмо со ссылкой отправлено.');
});
