<?php

use App\Livewire\OnlineUsers;
use App\Models\User;
use Database\Seeders\DashboardWidgetSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function d5OnlineUser(string $name, Carbon $lastSeenAt, bool $profilePublic = true): User
{
    return User::factory()->create([
        'name' => $name,
        'email_verified_at' => now(),
        'is_profile_public' => $profilePublic,
        'last_seen_at' => $lastSeenAt,
    ]);
}

function d5OnlineViewer(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(DashboardWidgetSeeder::class);
});

it('shows the online widget only to authenticated users and places it after the activity feed', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('Игроки онлайн');

    $viewer = d5OnlineViewer();
    $this->actingAs($viewer)
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Общий чат', 'Ближайшие события', 'Активность', 'Игроки онлайн']);
});

it('counts only active users seen within the last five minutes', function () {
    $viewer = d5OnlineViewer();
    d5OnlineUser('OnlineFresh', now()->subMinutes(4));
    d5OnlineUser('OnlineBoundary', now()->subMinutes(5));
    d5OnlineUser('OnlineStale', now()->subMinutes(6));

    Livewire::actingAs($viewer)
        ->test(OnlineUsers::class)
        ->assertSee('2')
        ->assertSee('OnlineFresh')
        ->assertSee('OnlineBoundary')
        ->assertDontSee('OnlineStale');
});

it('orders online users by latest activity and renders no more than five avatars', function () {
    $viewer = d5OnlineViewer();
    foreach (range(1, 7) as $number) {
        d5OnlineUser("OnlinePlayer{$number}", now()->subSeconds($number * 10));
    }

    $component = Livewire::actingAs($viewer)->test(OnlineUsers::class);
    $component->assertSeeInOrder([
        'OnlinePlayer1',
        'OnlinePlayer2',
        'OnlinePlayer3',
        'OnlinePlayer4',
        'OnlinePlayer5',
    ]);

    expect(substr_count($component->html(), 'class="avatar avatar--placeholder"'))->toBe(5)
        ->and($component->html())->not->toContain('OnlinePlayer6');
});

it('links the count to the directory and links only public online profiles', function () {
    $viewer = d5OnlineViewer();
    $public = d5OnlineUser('OnlinePublic', now());
    $private = d5OnlineUser('OnlinePrivate', now(), false);

    $component = Livewire::actingAs($viewer)->test(OnlineUsers::class);
    $component->assertSee(route('players.directory'))
        ->assertSee(route('players.show', $public->name))
        ->assertDontSee(route('players.show', $private->name));
});

it('uses the sixty-second polling interval', function () {
    Livewire::actingAs(d5OnlineViewer())
        ->test(OnlineUsers::class)
        ->assertSee('wire:poll.60s="refreshOnlineUsers"', false);
});
