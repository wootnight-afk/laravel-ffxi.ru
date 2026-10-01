<?php

use App\Models\User;

it('returns the same response for existing and missing email on password reset', function () {
    User::factory()->create(['email' => 'exists@example.com']);

    $responseExisting = $this->post(route('password.email'), [
        'email' => 'exists@example.com',
    ]);

    $responseMissing = $this->post(route('password.email'), [
        'email' => 'missing@example.com',
    ]);

    $responseExisting->assertRedirect();
    $responseMissing->assertRedirect();

    expect(session('status'))->toBe(session('status'));
    expect(session('status'))->toContain('Если такой аккаунт существует');
});

it('returns the same response for existing and missing email on verification resend', function () {
    $verified = User::factory()->create(['email_verified_at' => now()]);
    $unverified = User::factory()->create(['email_verified_at' => null]);

    $responseVerified = $this->actingAs($verified)->post(route('verification.resend'));
    $responseUnverified = $this->actingAs($unverified)->post(route('verification.resend'));

    expect($responseVerified->status())->toBeIn([200, 302]);
    expect($responseUnverified->status())->toBeIn([200, 302]);
});

it('returns generic login error for unknown email and wrong password', function () {
    User::factory()->create(['email' => 'exists@example.com']);

    $this->post(route('login.post'), [
        'email' => 'nobody@example.com',
        'password' => 'whatever',
    ])->assertSessionHasErrors(['email' => 'Неверный email или пароль.']);

    $this->post(route('login.post'), [
        'email' => 'exists@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['email' => 'Неверный email или пароль.']);
});
