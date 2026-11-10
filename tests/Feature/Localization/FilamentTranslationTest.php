<?php

declare(strict_types=1);

use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\SettingsPage;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\UserResource;
use App\Models\User;

it('resolves filament navigation groups from stable keys and translates labels', function () {
    expect(UserResource::getNavigationGroup())->toBe('community')
        ->and(NewsResource::getNavigationGroup())->toBe('content')
        ->and(SettingsPage::getNavigationGroup())->toBe('system')
        ->and(AdminDashboard::getNavigationGroup())->toBe('main');

    app()->setLocale('en');

    expect(__('filament.navigation.main'))->toBe('Main')
        ->and(__('filament.navigation.community'))->toBe('Community')
        ->and(__('filament.navigation.content'))->toBe('Content')
        ->and(__('filament.navigation.system'))->toBe('System')
        ->and(UserResource::getNavigationLabel())->toBe('Users')
        ->and(UserResource::getModelLabel())->toBe('User');

    app()->setLocale('ru');

    expect(__('filament.navigation.main'))->toBe('Основное')
        ->and(__('filament.navigation.community'))->toBe('Сообщество')
        ->and(__('filament.navigation.content'))->toBe('Контент')
        ->and(__('filament.navigation.system'))->toBe('Система')
        ->and(UserResource::getNavigationLabel())->toBe('Пользователи')
        ->and(UserResource::getModelLabel())->toBe('Пользователь');
});

it('renders the admin navigation in the selected locale', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'locale' => 'en',
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Community')
        ->assertSee('Content')
        ->assertSee('System')
        ->assertDontSee('Сообщество');

    $admin->update(['locale' => 'ru']);

    $this->actingAs($admin->fresh())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Сообщество')
        ->assertSee('Контент')
        ->assertSee('Система')
        ->assertDontSee('Community');
});
