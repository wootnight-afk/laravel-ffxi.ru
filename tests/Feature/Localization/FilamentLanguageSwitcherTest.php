<?php

declare(strict_types=1);

use App\Models\User;

function makeSwitcherUser(string $role = 'admin'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('renders the language switcher in the admin topbar', function () {
    $admin = makeSwitcherUser();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('action="'.route('locale.update').'"', false)
        ->assertSee('name="locale"', false)
        ->assertSee('value="ru"', false)
        ->assertSee('value="en"', false)
        ->assertSee('aria-label="'.__('site.locale.switch_label').'"', false);
});

it('switches the admin panel language and persists the user preference', function () {
    $admin = makeSwitcherUser();

    $this->actingAs($admin)
        ->post(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect();

    expect($admin->refresh()->locale)->toBe('en');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Control panel')
        ->assertDontSee('Панель управления');
});

it('switches the admin panel back to russian', function () {
    $admin = makeSwitcherUser();
    $admin->forceFill(['locale' => 'en'])->save();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Control panel');

    $this->actingAs($admin)
        ->post(route('locale.update'), ['locale' => 'ru'])
        ->assertRedirect();

    expect($admin->refresh()->locale)->toBe('ru');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Панель управления')
        ->assertDontSee('Control panel');
});

it('rejects an unsupported locale from the switcher', function () {
    $admin = makeSwitcherUser();

    $this->actingAs($admin)
        ->post(route('locale.update'), ['locale' => 'de'])
        ->assertSessionHasErrors('locale');

    expect($admin->refresh()->locale)->toBeNull();
});
