<?php

declare(strict_types=1);

use App\Filament\Resources\RankResource\Pages\CreateRank;
use App\Filament\Resources\RankResource\Pages\EditRank;
use App\Filament\Resources\RankResource\Pages\ListRanks;
use App\Models\User;
use App\Models\UserRank;
use Livewire\Livewire;

function makeRankPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeRankRecord(array $overrides = []): UserRank
{
    return UserRank::create(array_merge([
        'key' => 'rank-'.uniqid(),
        'title' => 'Rank '.uniqid(),
        'rating' => 100,
        'is_active' => true,
    ], $overrides));
}

// ------------------------------------------------------------------
// Access — permission-based (ranks.manage is admin-only)
// ------------------------------------------------------------------

it('allows admins to access the rank resource', function () {
    $admin = makeRankPanelUser('admin');

    $this->actingAs($admin)->get('/admin/ranks')->assertOk();
});

it('denies editors access to the rank resource', function () {
    $editor = makeRankPanelUser('editor');

    $this->actingAs($editor)->get('/admin/ranks')->assertForbidden();
});

// ------------------------------------------------------------------
// CRUD
// ------------------------------------------------------------------

it('creates a rank with a rating as admin', function () {
    $admin = makeRankPanelUser('admin');

    Livewire::actingAs($admin)
        ->test(CreateRank::class)
        ->set('data.key', 'veteran')
        ->set('data.title', 'Ветеран')
        ->set('data.rating', 500)
        ->set('data.is_active', true)
        ->call('create')
        ->assertHasNoErrors();

    $rank = UserRank::query()->where('key', 'veteran')->firstOrFail();

    expect($rank->rating)->toBe(500)
        ->and($rank->title)->toBe('Ветеран');
});

it('updates the rating of a rank', function () {
    $admin = makeRankPanelUser('admin');
    $rank = makeRankRecord(['rating' => 100]);

    Livewire::actingAs($admin)
        ->test(EditRank::class, ['record' => $rank->getKey()])
        ->set('data.rating', 999)
        ->call('save')
        ->assertHasNoErrors();

    expect($rank->refresh()->rating)->toBe(999);
});

it('does not expose an export action in the rank resource', function () {
    $admin = makeRankPanelUser('admin');

    Livewire::actingAs($admin)
        ->test(ListRanks::class)
        ->assertTableActionDoesNotExist('export')
        ->assertTableBulkActionDoesNotExist('export');
});
