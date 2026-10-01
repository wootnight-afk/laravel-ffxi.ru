<?php

use App\Enums\UserStatus;
use App\Models\User;

it('redirects guests from all player pages to login', function () {
    $target = User::factory()->create(['name' => 'Bugor']);

    $this->get(route('players.dashboard'))->assertRedirect(route('login'));
    $this->get(route('players.directory'))->assertRedirect(route('login'));
    $this->get(route('players.show', $target->name))->assertRedirect(route('login'));
});

it('shows dashboard to authenticated users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    $response = $this->actingAs($user)->get(route('players.dashboard'));

    $response->assertOk();
    $response->assertSee('Дашборд сообщества');
    $response->assertSee($user->name);
});

it('shows directory to authenticated users', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'name' => 'Viewer',
    ]);
    $user->assignRole('user');

    $target = User::factory()->create(['name' => 'Bugor']);

    $response = $this->actingAs($user)->get(route('players.directory'));

    $response->assertOk();
    $response->assertSee('Bugor');
});

it('filters directory by nickname', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    User::factory()->create(['name' => 'Bugor']);
    User::factory()->create(['name' => 'Monarch']);

    $response = $this->actingAs($user)->get(route('players.directory', ['q' => 'Bug']));

    $response->assertOk();
    $response->assertSee('Bugor');
    $response->assertDontSee('Monarch');
});

it('shows closed profile with nick only', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->assignRole('user');

    $target = User::factory()->create([
        'name' => 'Jumxi',
        'is_profile_public' => false,
        'legend' => 'Very long legend text',
    ]);

    $response = $this->actingAs($viewer)->get(route('players.show', $target->name));

    $response->assertOk();
    $response->assertSee('Jumxi');
    $response->assertSee('Профиль закрыт');
    $response->assertDontSee('Very long legend text');
});

it('shows open profile with legend', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->assignRole('user');

    $target = User::factory()->create([
        'name' => 'Scaevola',
        'is_profile_public' => true,
        'legend' => 'Legend text of the player',
    ]);

    // Принудительно пересчитываем body_html, потому что create() не сработал через booted
    $target->forceFill(['legend' => 'Legend text of the player'])->save();
    $target->refresh();

    $response = $this->actingAs($viewer)->get(route('players.show', $target->name));

    $response->assertOk();
    $response->assertSee('Scaevola');
    $response->assertDontSee('Профиль закрыт');
});

it('returns 404 for deleted account', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->assignRole('user');

    $target = User::factory()->create([
        'name' => 'Ghost',
        'status' => UserStatus::DeletionRequested,
    ]);

    $this->actingAs($viewer)
        ->get(route('players.show', $target->name))
        ->assertNotFound();
});
