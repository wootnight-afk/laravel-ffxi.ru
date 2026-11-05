<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\AdminAuditLog;
use App\Models\User;
use Livewire\Livewire;

function makePreferencesAdmin(string $role = 'admin'): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

function makePreferencesTarget(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now()->startOfMinute(),
    ], $overrides));
    $user->assignRole('user');

    return $user;
}

it('exposes locale and timezone fields in the user resource', function () {
    $admin = makePreferencesAdmin();
    $target = makePreferencesTarget([
        'locale' => 'en',
        'timezone' => 'Asia/Tokyo',
    ]);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->assertFormFieldExists('locale')
        ->assertFormFieldExists('timezone')
        ->assertSet('data.locale', 'en')
        ->assertSet('data.timezone', 'Asia/Tokyo');
});

it('lets an admin update locale and timezone and records an audit entry', function () {
    $admin = makePreferencesAdmin();
    $target = makePreferencesTarget([
        'locale' => 'ru',
        'timezone' => 'Europe/Moscow',
    ]);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.locale', 'en')
        ->set('data.timezone', 'Asia/Tokyo')
        ->call('save')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->locale)->toBe('en')
        ->and($target->timezone)->toBe('Asia/Tokyo');

    $log = AdminAuditLog::query()
        ->where('action', 'user.updated')
        ->where('subject_type', User::class)
        ->where('subject_id', $target->getKey())
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->getKey())
        ->and($log->new)->toMatchArray([
            'locale' => 'en',
            'timezone' => 'Asia/Tokyo',
        ]);
});

it('denies editors access to the user resource', function () {
    $editor = makePreferencesAdmin('editor');
    $target = makePreferencesTarget();

    $this->actingAs($editor)
        ->get("/admin/users/{$target->getKey()}/edit")
        ->assertForbidden();
});

it('keeps invalid stored preferences visible without resetting them', function () {
    $admin = makePreferencesAdmin();
    $target = makePreferencesTarget([
        'locale' => 'xx',
        'timezone' => 'Mars/Olympus',
    ]);

    $this->actingAs($admin)
        ->get("/admin/users/{$target->getKey()}/edit")
        ->assertOk()
        ->assertSee('не сбрасывается автоматически');

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->assertSet('data.locale', 'xx')
        ->assertSet('data.timezone', 'Mars/Olympus');

    expect($target->refresh()->locale)->toBe('xx')
        ->and($target->timezone)->toBe('Mars/Olympus');
});

it('allows an admin to manually correct invalid stored preferences', function () {
    $admin = makePreferencesAdmin();
    $target = makePreferencesTarget([
        'locale' => 'xx',
        'timezone' => 'Mars/Olympus',
    ]);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.locale', 'ru')
        ->set('data.timezone', 'Europe/Moscow')
        ->call('save')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->locale)->toBe('ru')
        ->and($target->timezone)->toBe('Europe/Moscow');
});

it('rejects invalid values submitted through the admin form', function () {
    $admin = makePreferencesAdmin();
    $target = makePreferencesTarget();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.locale', 'de')
        ->set('data.timezone', 'UTC+3')
        ->call('save')
        ->assertHasErrors(['data.locale', 'data.timezone']);

    $target->refresh();

    expect($target->locale)->toBeNull()
        ->and($target->timezone)->toBeNull();
});
