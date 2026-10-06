<?php

declare(strict_types=1);

use App\Filament\Resources\DashboardWidgetResource\Pages\CreateDashboardWidget;
use App\Filament\Resources\DashboardWidgetResource\Pages\EditDashboardWidget;
use App\Filament\Resources\DashboardWidgetResource\Pages\ListDashboardWidgets;
use App\Models\DashboardWidget;
use App\Models\User;
use Livewire\Livewire;

function makeWidgetPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeWidgetRecord(array $overrides = []): DashboardWidget
{
    return DashboardWidget::create(array_merge([
        'key' => 'widget-'.uniqid(),
        'title' => 'Widget '.uniqid(),
        'type' => 'community_chat',
        'column_span' => 1,
        'is_active' => true,
    ], $overrides));
}

// ------------------------------------------------------------------
// Access — permission-based (widgets.manage is admin-only)
// ------------------------------------------------------------------

it('allows admins to access the dashboard widget resource', function () {
    $admin = makeWidgetPanelUser('admin');

    $this->actingAs($admin)->get('/admin/dashboard-widgets')->assertOk();
});

it('denies editors access to the dashboard widget resource', function () {
    $editor = makeWidgetPanelUser('editor');

    $this->actingAs($editor)->get('/admin/dashboard-widgets')->assertForbidden();
});

// ------------------------------------------------------------------
// CRUD
// ------------------------------------------------------------------

it('creates a dashboard widget with settings as admin', function () {
    $admin = makeWidgetPanelUser('admin');

    Livewire::actingAs($admin)
        ->test(CreateDashboardWidget::class)
        ->set('data.key', 'community_chat')
        ->set('data.title', 'Чат сообщества')
        ->set('data.type', 'community_chat')
        ->set('data.column_span', 2)
        ->set('data.settings', ['interval' => '30'])
        ->set('data.is_active', true)
        ->call('create')
        ->assertHasNoErrors();

    $widget = DashboardWidget::query()->where('key', 'community_chat')->firstOrFail();

    expect($widget->column_span)->toBe(2)
        ->and($widget->settings)->toBe(['interval' => '30']);
});

it('updates widget settings through the edit page', function () {
    $admin = makeWidgetPanelUser('admin');
    $widget = makeWidgetRecord(['settings' => ['interval' => '30']]);

    Livewire::actingAs($admin)
        ->test(EditDashboardWidget::class, ['record' => $widget->getKey()])
        ->set('data.settings', ['interval' => '60'])
        ->call('save')
        ->assertHasNoErrors();

    expect($widget->refresh()->settings)->toBe(['interval' => '60']);
});

it('does not expose an export action in the dashboard widget resource', function () {
    $admin = makeWidgetPanelUser('admin');

    Livewire::actingAs($admin)
        ->test(ListDashboardWidgets::class)
        ->assertTableActionDoesNotExist('export')
        ->assertTableBulkActionDoesNotExist('export');
});
