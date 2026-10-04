<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRank;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function makeRank(array $overrides = []): UserRank
{
    return UserRank::create(array_merge([
        'key' => 'test-'.uniqid(),
        'title' => 'Тестовый ранг',
        'icon' => '🌟',
        'rating' => 50,
        'sort_order' => 0,
        'is_active' => true,
    ], $overrides));
}

function makePlayerWithRank(?UserRank $rank = null): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_profile_public' => true,
        'rank_id' => $rank?->id,
    ]);
    $user->assignRole('user');

    return $user;
}

beforeEach(function () {
    Cache::flush();
    app(SettingsRepository::class)->set('ranks_enabled', true);
});

it('does not render rank to a guest viewer via component', function () {
    $rank = makeRank();
    $player = makePlayerWithRank($rank);

    // No actingAs: viewer is a guest.
    $html = Blade::render(
        '<x-user-identity :user="$user" />',
        ['user' => $player]
    );

    expect($html)->not->toContain($rank->icon);
    expect($html)->not->toContain($rank->title);
});

it('redirects guests from player pages to login', function () {
    $player = makePlayerWithRank();

    get(route('players.show', $player->name))
        ->assertRedirect(route('login'));
});

it('shows rank to authenticated users on a public profile', function () {
    $rank = makeRank();
    $player = makePlayerWithRank($rank);
    $viewer = makePlayerWithRank();

    actingAs($viewer)
        ->get(route('players.show', $player->name))
        ->assertOk()
        ->assertSee($rank->icon)
        ->assertSee($rank->title);
});

it('hides rank everywhere when ranks_enabled is false', function () {
    $rank = makeRank();
    $player = makePlayerWithRank($rank);
    $viewer = makePlayerWithRank();

    app(SettingsRepository::class)->set('ranks_enabled', false);

    actingAs($viewer)
        ->get(route('players.show', $player->name))
        ->assertOk()
        ->assertDontSee($rank->icon)
        ->assertDontSee($rank->title);
});

it('hides rank in chat context via component', function () {
    $rank = makeRank();
    $player = makePlayerWithRank($rank);

    $html = Blade::render(
        '<x-user-identity :user="$user" context="chat" />',
        ['user' => $player]
    );

    expect($html)->not->toContain($rank->icon);
    expect($html)->not->toContain($rank->title);
});

it('nullifies user rank_id when rank is deleted', function () {
    $rank = makeRank();
    $player = makePlayerWithRank($rank);

    $rank->delete();

    expect($player->fresh()->rank_id)->toBeNull();
});
