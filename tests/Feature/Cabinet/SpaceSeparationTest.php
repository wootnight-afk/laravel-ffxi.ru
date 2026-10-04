<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function makeSpaceUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'is_profile_public' => true,
    ], $overrides));
    $user->assignRole('user');

    return $user;
}

beforeEach(function () {
    Cache::flush();
    app(SettingsRepository::class)->set('guest_sections', [
        'home' => true,
        'news' => true,
        'gallery' => true,
        'contacts' => true,
        'events' => true,
        'players' => false,
        'player_profiles' => false,
    ]);
});

it('redirects guests from cabinet to login', function () {
    get(route('cabinet.show'))->assertRedirect(route('login'));
    get(route('cabinet.tab', ['tab' => 'profile']))->assertRedirect(route('login'));
});

it('shows cabinet only to the authenticated user', function () {
    $a = makeSpaceUser(['name' => 'OwnerA'.uniqid()]);
    $b = makeSpaceUser(['name' => 'ForeignB'.uniqid()]);

    actingAs($a)
        ->get(route('cabinet.show'))
        ->assertOk()
        ->assertSee('Мой кабинет')
        ->assertDontSee($b->name);
});

it('shows edit affordance to the owner on own player page', function () {
    $owner = makeSpaceUser();

    actingAs($owner)
        ->get(route('players.show', $owner->name))
        ->assertOk()
        ->assertSee('Редактировать');
});

it('hides edit affordance from other users on a foreign player page', function () {
    $owner = makeSpaceUser(['is_profile_public' => true]);
    $viewer = makeSpaceUser();

    actingAs($viewer)
        ->get(route('players.show', $owner->name))
        ->assertOk()
        ->assertDontSee('Редактировать');
});

it('allows owner to view own closed profile', function () {
    $owner = makeSpaceUser(['is_profile_public' => false]);

    actingAs($owner)
        ->get(route('players.show', $owner->name))
        ->assertOk();
});
