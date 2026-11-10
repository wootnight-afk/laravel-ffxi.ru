<?php

declare(strict_types=1);

use App\Models\User;

function cabinetUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
    ], $overrides));
    $user->assignRole('user');

    return $user;
}

it('renders the cabinet tabs in english', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('cabinet.show'))
        ->assertOk()
        ->assertSee('My cabinet')
        ->assertSee('Danger zone')
        ->assertDontSee('Мой кабинет');
});

it('renders the profile tab in english', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'profile']))
        ->assertOk()
        ->assertSee('Language and timezone')
        ->assertSee('Public profile')
        ->assertSee('Save timezone')
        ->assertDontSee('Язык и часовой пояс');
});

it('renders the security tab in english', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'security']))
        ->assertOk()
        ->assertSee('Two-factor authentication (MFA)')
        ->assertSee('Change password')
        ->assertSee('Set up MFA')
        ->assertDontSee('Двухфакторная аутентификация');
});

it('renders the social tab in english', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'social']))
        ->assertOk()
        ->assertSee('Social networks')
        ->assertSee('Add link')
        ->assertSee('Platform')
        ->assertDontSee('Социальные сети');
});

it('renders the player news tab in english', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'news']))
        ->assertOk()
        ->assertSee('My news')
        ->assertSee('Write news')
        ->assertSee('Create draft')
        ->assertDontSee('Мои новости');
});

it('renders the player gallery tab in english', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'gallery']))
        ->assertOk()
        ->assertSee('My gallery')
        ->assertSee('Create album')
        ->assertSee('You have no albums yet.')
        ->assertDontSee('Моя галерея');
});

it('renders the danger zone in english', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('cabinet.tab', ['tab' => 'danger']))
        ->assertOk()
        ->assertSee('Danger zone')
        ->assertSee('Suspend account')
        ->assertSee('Request account deletion')
        ->assertDontSee('Опасная зона');
});

it('renders the cabinet tabs in russian', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'ru'])
        ->get(route('cabinet.tab', ['tab' => 'gallery']))
        ->assertOk()
        ->assertSee('Моя галерея')
        ->assertSee('Создать альбом')
        ->assertDontSee('My gallery');
});

it('localizes the profile validation messages', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->post(route('cabinet.profile.update'), ['race' => 'unknown-race'])
        ->assertSessionHasErrors(['race' => 'Invalid race.']);
});

it('localizes the social link validation messages', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->post(route('cabinet.social.store'), ['type' => 'not-a-platform'])
        ->assertSessionHasErrors(['type' => 'Unknown platform.']);
});

it('localizes the danger zone validation messages', function () {
    $user = cabinetUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->post(route('cabinet.danger.request'), [])
        ->assertSessionHasErrors(['reason' => 'Specify a reason.']);
});

it('renders the mfa challenge page in english', function () {
    $user = cabinetUser();
    $user->saveAppAuthenticationSecret('KRSXG5CTMVRXEZLU');

    $this->actingAs($user->refresh())
        ->withSession(['locale' => 'en'])
        ->get(route('mfa.challenge'))
        ->assertOk()
        ->assertSee('Sign-in confirmation')
        ->assertSee('Use a recovery code')
        ->assertDontSee('Подтверждение входа');
});
