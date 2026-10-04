<?php

use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

function d6DashboardUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function d6Widget(string $key, string $type, int $order, array $attributes = []): DashboardWidget
{
    return DashboardWidget::query()->create(array_merge([
        'key' => $key,
        'title' => $key,
        'type' => $type,
        'sort_order' => $order,
        'column_span' => 1,
        'is_active' => true,
        'settings' => null,
    ], $attributes));
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('renders active dashboard widgets in database order', function () {
    d6Widget('activity_feed', 'activity_feed', 40);
    d6Widget('events_board', 'events_board', 20);
    d6Widget('online_users', 'online_users', 50);
    d6Widget('community_chat', 'community_chat', 10);

    $this->actingAs(d6DashboardUser())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSeeInOrder([
            'Общий чат',
            'Ближайшие события',
            'Активность',
            'Игроки онлайн',
        ]);
});

it('does not render inactive dashboard widgets', function () {
    d6Widget('inactive_online', 'online_users', 10, ['is_active' => false]);

    $this->actingAs(d6DashboardUser())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertDontSee('Игроки онлайн');
});

it('skips and warns about unsupported dashboard widget types', function () {
    d6Widget('future_chart', 'api_chart', 10);
    Log::shouldReceive('warning')
        ->once()
        ->with('Skipped unsupported dashboard widget type.', Mockery::on(
            fn (array $context): bool => $context['widget_key'] === 'future_chart'
                && $context['widget_type'] === 'api_chart',
        ));

    $this->actingAs(d6DashboardUser())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertDontSee('future_chart');
});

it('applies database column spans one through three to widget wrapper classes', function () {
    d6Widget('chat_span', 'community_chat', 10, ['column_span' => 1]);
    d6Widget('events_span', 'events_board', 20, ['column_span' => 2]);
    d6Widget('online_span', 'online_users', 30, ['column_span' => 3]);

    $response = $this->actingAs(d6DashboardUser())
        ->get(route('players.dashboard'))
        ->assertOk();

    $response->assertSee('dashboard-widget--span-1')
        ->assertSee('dashboard-widget--span-2')
        ->assertSee('dashboard-widget--span-3');
});

it('renders each supported community widget type', function () {
    d6Widget('chat_known', 'community_chat', 10);
    d6Widget('events_known', 'events_board', 20);
    d6Widget('activity_known', 'activity_feed', 30);
    d6Widget('online_known', 'online_users', 40);

    $this->actingAs(d6DashboardUser())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('Общий чат')
        ->assertSee('Ближайшие события')
        ->assertSee('Активность')
        ->assertSee('Игроки онлайн');
});

it('renders an empty dashboard grid when no widgets are configured', function () {
    $this->actingAs(d6DashboardUser())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('dashboard-grid')
        ->assertDontSee('Общий чат')
        ->assertDontSee('Ближайшие события')
        ->assertDontSee('Активность')
        ->assertDontSee('Игроки онлайн');
});

it('clamps invalid column spans to the supported grid range', function () {
    d6Widget('oversized_span', 'online_users', 10, ['column_span' => 9]);

    $this->actingAs(d6DashboardUser())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('dashboard-widget--span-3')
        ->assertDontSee('dashboard-widget--span-9');
});

it('uses the widget id as a deterministic key for repeated component types', function () {
    $first = d6Widget('online_one', 'online_users', 10);
    $second = d6Widget('online_two', 'online_users', 20);

    $this->actingAs(d6DashboardUser())
        ->get(route('players.dashboard'))
        ->assertOk()
        ->assertSee('dashboard-online-users-'.$first->id)
        ->assertSee('dashboard-online-users-'.$second->id);
});
