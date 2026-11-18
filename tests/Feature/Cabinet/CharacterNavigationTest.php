<?php

use App\Models\User;
use App\Services\SitePageRegistry;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
});

it('places the characters item right after the profile tab', function () {
    $html = $this->actingAs($this->user)
        ->get(route('cabinet.show'))
        ->assertOk()
        ->getContent();

    $profile = strpos($html, route('cabinet.tab', ['tab' => 'profile']));
    $characters = strpos($html, route('cabinet.characters.index'));
    $social = strpos($html, route('cabinet.tab', ['tab' => 'social']));

    expect($profile)->not->toBeFalse()
        ->and($characters)->not->toBeFalse()
        ->and($social)->not->toBeFalse()
        ->and($characters)->toBeGreaterThan($profile)
        ->and($characters)->toBeLessThan($social);
});

it('does not keep a characters item in the players navigation', function () {
    $response = $this->actingAs($this->user)->get(route('players.dashboard'));

    $response->assertOk();
    $response->assertDontSee(route('players.characters.index'), false);
});

it('redirects the legacy url with a temporary redirect and keeps the query string', function () {
    $this->actingAs($this->user)
        ->get(route('players.characters.index'))
        ->assertStatus(302)
        ->assertRedirect(route('cabinet.characters.index'));

    $this->actingAs($this->user)
        ->get('/players/characters?server=asura')
        ->assertStatus(302)
        ->assertRedirect(route('cabinet.characters.index', ['server' => 'asura']));
});

it('rejects write methods on the legacy url', function () {
    $this->actingAs($this->user)
        ->post('/players/characters', ['name' => 'Bugor'])
        ->assertStatus(405);

    // The path has no DELETE route any more, so the action cannot run at all.
    $this->actingAs($this->user)
        ->delete('/players/characters/1')
        ->assertStatus(404);
});

it('registers the cabinet character pages in the site registry', function () {
    $registry = app(SitePageRegistry::class);

    $routes = collect($registry->records())->pluck('route');

    expect($routes)->toContain('cabinet.characters.index')
        ->and($routes)->toContain('cabinet.characters.show');

    $classification = collect($registry->classification());

    // The legacy URL is still a real GET route, so it stays a classified
    // record: the only thing that must not happen is losing it entirely.
    expect($classification->firstWhere('name', 'players.characters.index')['category'] ?? null)
        ->not->toBeIn(['unclassified', 'missing']);

    expect($classification->where('category', 'missing')->pluck('name')->all())
        ->not->toContain('cabinet.characters.index')
        ->not->toContain('cabinet.characters.show')
        ->not->toContain('players.characters.index');

    expect($classification->where('category', 'unclassified')->pluck('name')->all())
        ->not->toContain('cabinet.characters.index')
        ->not->toContain('cabinet.characters.show')
        ->not->toContain('players.characters.index');
});

it('renders the cabinet characters page in english', function () {
    $this->actingAs($this->user)
        ->withSession(['locale' => 'en'])
        ->get(route('cabinet.characters.index'))
        ->assertOk()
        ->assertSee(__('site.cabinet.characters.title', [], 'en'));
});
