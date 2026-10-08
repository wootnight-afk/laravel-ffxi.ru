<?php

declare(strict_types=1);

use App\Filament\Pages\SettingsPage;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Notifications\MfaResetByAdminNotification;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

const MFA_MGMT_SECRET = 'JBSWY3DPEHPK3PXP';

function mfaMgmtUser(string $role, bool $withMfa = false): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    if ($withMfa) {
        $user->saveAppAuthenticationSecret(MFA_MGMT_SECRET);
    }

    return $user->refresh();
}

function mfaMgmtAdmin(): User
{
    return mfaMgmtUser('admin');
}

beforeEach(function () {
    Cache::flush();
    Notification::fake();
});

// ------------------------------------------------------------------
// Permission mfa.manage
// ------------------------------------------------------------------

it('creates the mfa.manage permission in the seeder', function () {
    expect(Permission::query()->where('name', 'mfa.manage')->exists())->toBeTrue();
});

it('assigns mfa.manage to admin only', function () {
    expect(mfaMgmtUser('admin')->hasPermissionTo('mfa.manage'))->toBeTrue()
        ->and(mfaMgmtUser('editor')->hasPermissionTo('mfa.manage'))->toBeFalse()
        ->and(mfaMgmtUser('user')->hasPermissionTo('mfa.manage'))->toBeFalse();
});

// ------------------------------------------------------------------
// Page and section access
// ------------------------------------------------------------------

it('renders the MFA management section for admins', function () {
    $admin = mfaMgmtAdmin();

    actingAs($admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('Управление MFA пользователей');

    expect(SettingsPage::canManageMfa())->toBeTrue();
});

it('denies editors access to the settings page', function () {
    $editor = mfaMgmtUser('editor');

    actingAs($editor)->get('/admin/settings')->assertForbidden();

    expect(SettingsPage::canAccess())->toBeFalse()
        ->and(SettingsPage::canManageMfa())->toBeFalse();
});

it('denies regular users access to the settings page', function () {
    $user = mfaMgmtUser('user');

    actingAs($user)->get('/admin/settings')->assertForbidden();
});

// ------------------------------------------------------------------
// Global toggle mfa_global_enabled
// ------------------------------------------------------------------

it('defaults mfa_global_enabled to true', function () {
    expect(app(SettingsRepository::class)->bool('mfa_global_enabled'))->toBeTrue();
});

it('disables MFA enforcement for everyone when the toggle is turned off', function () {
    $admin = mfaMgmtAdmin();
    $user = mfaMgmtUser('user', withMfa: true);

    // Sanity check: enforcement is active by default.
    actingAs($user)->get('/players')->assertRedirect(route('mfa.challenge'));

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.mfa_global_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->bool('mfa_global_enabled'))->toBeFalse();

    // Cache was flushed: enforcement is gone immediately.
    actingAs($user)->get('/players')->assertOk();
    actingAs(mfaMgmtUser('admin', withMfa: true))->get('/admin/users')->assertOk();
});

it('restores enforcement when the toggle is turned back on', function () {
    $admin = mfaMgmtAdmin();
    $user = mfaMgmtUser('user', withMfa: true);

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.mfa_global_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    actingAs($user)->get('/players')->assertOk();

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->set('data.mfa_global_enabled', true)
        ->call('save')
        ->assertHasNoErrors();

    actingAs($user)->get('/players')->assertRedirect(route('mfa.challenge'));
});

// ------------------------------------------------------------------
// User list
// ------------------------------------------------------------------

it('lists users with their roles and MFA status', function () {
    $admin = mfaMgmtAdmin();
    $withMfa = mfaMgmtUser('user', withMfa: true);
    $withoutMfa = mfaMgmtUser('editor');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->assertCanSeeTableRecords([$withMfa, $withoutMfa])
        ->assertSee($withMfa->email)
        ->assertSee($withoutMfa->email);
});

it('filters the list by MFA status', function () {
    $admin = mfaMgmtAdmin();
    $withMfa = mfaMgmtUser('user', withMfa: true);
    $withoutMfa = mfaMgmtUser('user');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->filterTable('mfa_status', 'on')
        ->assertCanSeeTableRecords([$withMfa])
        ->assertCanNotSeeTableRecords([$withoutMfa])
        ->filterTable('mfa_status', 'off')
        ->assertCanSeeTableRecords([$withoutMfa])
        ->assertCanNotSeeTableRecords([$withMfa]);
});

it('filters the list by role', function () {
    $admin = mfaMgmtAdmin();
    $editor = mfaMgmtUser('editor');
    $regular = mfaMgmtUser('user');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->filterTable('roles', 'editor')
        ->assertCanSeeTableRecords([$editor])
        ->assertCanNotSeeTableRecords([$regular]);
});

it('searches the list by name and email', function () {
    $admin = mfaMgmtAdmin();
    $target = User::factory()->create([
        'name' => 'ZzUniquePlayerName',
        'email' => 'zz-unique-player@example.com',
    ]);
    $target->assignRole('user');

    $other = mfaMgmtUser('user');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->searchTable('ZzUniquePlayerName')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('paginates the user list by 25 per page', function () {
    $admin = mfaMgmtAdmin();

    User::factory()->count(30)->create()->each(fn (User $user) => $user->assignRole('user'));

    $component = Livewire::actingAs($admin)->test(SettingsPage::class);

    expect($component->instance()->getTable()->getDefaultPaginationPageOption())->toBe(25)
        ->and($component->instance()->getTable()->getRecords())->toHaveCount(25);
});

// ------------------------------------------------------------------
// Row action: reset MFA
// ------------------------------------------------------------------

it('resets the MFA secret and recovery codes after re-auth', function () {
    $admin = mfaMgmtAdmin();
    $target = mfaMgmtUser('user', withMfa: true);
    $target->saveAppAuthenticationRecoveryCodes([Hash::make('recovery-one')]);

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->mountTableAction('resetMfa', $target)
        ->set('mountedActions.0.data.password', 'password')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $target->refresh();

    expect($target->getAppAuthenticationSecret())->toBeNull()
        ->and($target->getAppAuthenticationRecoveryCodes())->toBeNull();
});

it('requires the current admin password before resetting MFA', function () {
    $admin = mfaMgmtAdmin();
    $target = mfaMgmtUser('user', withMfa: true);

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->mountTableAction('resetMfa', $target)
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['password']);

    expect($target->refresh()->getAppAuthenticationSecret())->toBe(MFA_MGMT_SECRET);
});

it('blocks the reset when the password is wrong', function () {
    $admin = mfaMgmtAdmin();
    $target = mfaMgmtUser('user', withMfa: true);

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->mountTableAction('resetMfa', $target)
        ->set('mountedActions.0.data.password', 'not-the-password')
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['password']);

    expect($target->refresh()->getAppAuthenticationSecret())->toBe(MFA_MGMT_SECRET)
        ->and(AdminAuditLog::query()->where('action', 'mfa.admin_reset')->count())->toBe(0);
});

it('records an audit entry without secrets and with the IP', function () {
    $admin = mfaMgmtAdmin();
    $target = mfaMgmtUser('user', withMfa: true);
    $target->saveAppAuthenticationRecoveryCodes([Hash::make('recovery-one')]);

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->mountTableAction('resetMfa', $target)
        ->set('mountedActions.0.data.password', 'password')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $log = AdminAuditLog::query()->where('action', 'mfa.admin_reset')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->getKey())
        ->and($log->subject_id)->toBe($target->getKey())
        ->and($log->old)->toBe(['mfa_enabled' => true])
        ->and($log->new)->toBe(['mfa_enabled' => false])
        ->and($log->ip)->not->toBeNull();

    $payload = json_encode([$log->old, $log->new]);

    expect($payload)->not->toContain(MFA_MGMT_SECRET)
        ->and($payload)->not->toContain('recovery-one');
});

it('notifies the user without exposing secrets', function () {
    $admin = mfaMgmtAdmin();
    $target = mfaMgmtUser('user', withMfa: true);

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->mountTableAction('resetMfa', $target)
        ->set('mountedActions.0.data.password', 'password')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    Notification::assertSentTo($target, MfaResetByAdminNotification::class, function (MfaResetByAdminNotification $notification) use ($target): bool {
        $message = $notification->toMail($target);

        $content = implode("\n", array_filter([
            (string) $message->subject,
            ...$message->introLines,
            (string) $message->actionText,
            (string) $message->actionUrl,
            ...$message->outroLines,
        ]));

        return ! str_contains($content, MFA_MGMT_SECRET);
    });
});

it('disables the reset action for a user without MFA', function () {
    $admin = mfaMgmtAdmin();
    $target = mfaMgmtUser('user');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->assertTableActionDisabled('resetMfa', $target);
});

it('lets an admin reset their own MFA', function () {
    $admin = mfaMgmtAdmin();
    $admin->saveAppAuthenticationSecret(MFA_MGMT_SECRET);

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->mountTableAction('resetMfa', $admin->refresh())
        ->set('mountedActions.0.data.password', 'password')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($admin->refresh()->getAppAuthenticationSecret())->toBeNull();
});
