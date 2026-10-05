<?php

declare(strict_types=1);

use App\Filament\Pages\PermissionsMatrixPage;
use App\Models\User;
use App\Services\SettingsRepository;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function makeMatrixAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

function makeMatrixRoleUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ------------------------------------------------------------------
// Access
// ------------------------------------------------------------------

it('allows admins to access the matrix page and renders roles and sections', function () {
    $admin = makeMatrixAdmin();

    actingAs($admin)
        ->get('/admin/matrix')
        ->assertOk()
        ->assertSee('Матрица доступа')
        ->assertSee('Пользователь')
        ->assertSee('Редактор')
        ->assertSee('Администратор')
        ->assertSee('Гости')
        ->assertSee('Главная')
        ->assertSee('Профили игроков');
});

it('denies editor access to the matrix page by direct URL', function () {
    $editor = makeMatrixRoleUser('editor');

    actingAs($editor)->get('/admin/matrix')->assertForbidden();
});

it('denies regular users access to the matrix page', function () {
    $user = makeMatrixRoleUser('user');

    actingAs($user)->get('/admin/matrix')->assertForbidden();
});

it('reports canAccess false for editor and user roles', function () {
    expect(PermissionsMatrixPage::canAccess())->toBeFalse();

    actingAs(makeMatrixRoleUser('editor'));
    expect(PermissionsMatrixPage::canAccess())->toBeFalse();

    actingAs(makeMatrixAdmin());
    expect(PermissionsMatrixPage::canAccess())->toBeTrue();
});

// ------------------------------------------------------------------
// Role section permissions
// ------------------------------------------------------------------

it('removes a section permission from the user role and middleware sees it immediately', function () {
    $admin = makeMatrixAdmin();

    Livewire::actingAs($admin)
        ->test(PermissionsMatrixPage::class)
        ->set('data.roles.user.players', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::findByName('user', 'web')->hasPermissionTo('section.players.view'))->toBeFalse();

    $user = makeMatrixRoleUser('user');
    actingAs($user)->get(route('players.dashboard'))->assertForbidden();
});

it('grants a section permission to the user role and middleware sees it immediately', function () {
    $admin = makeMatrixAdmin();

    // user role starts without player_profiles access? It has it by default;
    // remove first to prove the toggle can restore it.
    Role::findByName('user', 'web')->revokePermissionTo('section.player_profiles.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Livewire::actingAs($admin)
        ->test(PermissionsMatrixPage::class)
        ->set('data.roles.user.player_profiles', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::findByName('user', 'web')->hasPermissionTo('section.player_profiles.view'))->toBeTrue();
});

it('updates editor section permissions without affecting editor content permissions', function () {
    $admin = makeMatrixAdmin();

    Livewire::actingAs($admin)
        ->test(PermissionsMatrixPage::class)
        ->set('data.roles.editor.news', false)
        ->call('save')
        ->assertHasNoErrors();

    $editor = Role::findByName('editor', 'web');

    expect($editor->hasPermissionTo('section.news.view'))->toBeFalse()
        ->and($editor->hasPermissionTo('news.manage_site'))->toBeTrue()
        ->and($editor->hasPermissionTo('comments.moderate'))->toBeTrue();
});

it('keeps admin granted every section regardless of submitted state', function () {
    $admin = makeMatrixAdmin();

    Livewire::actingAs($admin)
        ->test(PermissionsMatrixPage::class)
        ->call('save')
        ->assertHasNoErrors();

    $adminRole = Role::findByName('admin', 'web');

    foreach (PermissionsMatrixPage::SECTIONS as $section) {
        expect($adminRole->hasPermissionTo("section.{$section}.view"))->toBeTrue();
    }

    expect($adminRole->hasPermissionTo('users.manage'))->toBeTrue();
});

it('does not create permissions for unknown section keys', function () {
    $admin = makeMatrixAdmin();

    Livewire::actingAs($admin)
        ->test(PermissionsMatrixPage::class)
        ->set('data.roles.user.unknown_section', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Permission::where('name', 'section.unknown_section.view')->exists())->toBeFalse();
});

// ------------------------------------------------------------------
// Guest flags
// ------------------------------------------------------------------

it('flushes the settings cache and closes a guest section immediately', function () {
    $admin = makeMatrixAdmin();

    Livewire::actingAs($admin)
        ->test(PermissionsMatrixPage::class)
        ->set('data.guests.news', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('guest_sections')['news'])->toBeFalse();

    auth()->logout();
    get(route('news.index'))->assertNotFound();
});

it('opens a guest section immediately after saving', function () {
    $admin = makeMatrixAdmin();

    app(SettingsRepository::class)->set('guest_sections', [
        'home' => true,
        'news' => false,
        'gallery' => true,
        'contacts' => true,
        'events' => true,
        'players' => false,
        'player_profiles' => false,
    ]);

    get(route('news.index'))->assertNotFound();

    Livewire::actingAs($admin)
        ->test(PermissionsMatrixPage::class)
        ->set('data.guests.news', true)
        ->call('save')
        ->assertHasNoErrors();

    auth()->logout();
    get(route('news.index'))->assertOk();
});

it('persists all seven guest section flags on save', function () {
    $admin = makeMatrixAdmin();

    Livewire::actingAs($admin)
        ->test(PermissionsMatrixPage::class)
        ->set('data.guests.home', false)
        ->set('data.guests.players', true)
        ->call('save')
        ->assertHasNoErrors();

    $stored = app(SettingsRepository::class)->get('guest_sections');

    expect($stored)->toHaveCount(7)
        ->and($stored['home'])->toBeFalse()
        ->and($stored['players'])->toBeTrue();
});
