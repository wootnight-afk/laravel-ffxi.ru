<?php

use App\Models\User;

it('allows admin to access admin panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->can('panel.access'))->toBeTrue();
    expect($admin->can('users.manage'))->toBeTrue();
    expect($admin->can('settings.manage'))->toBeTrue();
});

it('allows editor only to content permissions', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    expect($editor->isEditor())->toBeTrue();
    expect($editor->can('panel.access'))->toBeTrue();
    expect($editor->can('news.manage_site'))->toBeTrue();
    expect($editor->can('comments.moderate'))->toBeTrue();

    // Editor НЕ имеет доступа к системным разделам
    expect($editor->can('users.manage'))->toBeFalse();
    expect($editor->can('settings.manage'))->toBeFalse();
    expect($editor->can('roles.manage'))->toBeFalse();
    expect($editor->can('matrix.manage'))->toBeFalse();
});

it('does not grant admin permissions to regular user', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    expect($user->isAdmin())->toBeFalse();
    expect($user->isEditor())->toBeFalse();
    expect($user->can('panel.access'))->toBeFalse();
    expect($user->can('users.manage'))->toBeFalse();
    expect($user->can('news.create_own'))->toBeTrue();
    expect($user->can('comments.create'))->toBeTrue();
});

it('denies access to admin panel gate for unauthenticated user', function () {
    // Filament route /admin появится на этапе 8.
    // Сейчас проверяем: гость не имеет permission panel.access.
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    expect($user->can('panel.access'))->toBeFalse();
});
