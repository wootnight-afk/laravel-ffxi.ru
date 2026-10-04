<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Notifications\AccountSuspendedNotification;
use App\Notifications\UserSuspendedForAdminNotification;
use Illuminate\Support\Facades\Notification;

function makeVerifiedSuspensionUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

beforeEach(function () {
    Notification::fake();
});

it('sets the suspended status', function () {
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'reason' => 'Taking a break',
        'confirm' => '1',
    ])->assertRedirect(route('home'));

    expect($user->fresh()->status)->toBe(UserStatus::Suspended);
});

it('records when the account was suspended', function () {
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'reason' => 'Taking a break',
        'confirm' => '1',
    ]);

    expect($user->fresh()->suspended_at)->not->toBeNull();
});

it('stores the suspension reason', function () {
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'reason' => 'Taking a break',
        'confirm' => '1',
    ]);

    expect($user->fresh()->suspension_reason)->toBe('Taking a break');
});

it('audits a suspension without recording the reason', function () {
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'reason' => 'Private reason',
        'confirm' => '1',
    ]);

    $log = AdminAuditLog::query()->where('action', 'account.suspended')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_id)->toBe($user->getKey())
        ->and($log->new['status'])->toBe(UserStatus::Suspended->value)
        ->and(json_encode($log->new))->not->toContain('Private reason');
});

it('sends a database notification to the suspended user', function () {
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'reason' => 'Taking a break',
        'confirm' => '1',
    ]);

    Notification::assertSentTo($user, AccountSuspendedNotification::class);

    $notification = new AccountSuspendedNotification('Taking a break', now()->toIso8601String());
    expect($notification->via($user))->toBe(['database'])
        ->and($notification->toArray($user))->toHaveKeys(['reason', 'suspended_at'])
        ->and($notification->toArray($user))->not->toHaveKey('email');
});

it('notifies all admins except the initiating user', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'reason' => 'Taking a break',
        'confirm' => '1',
    ]);

    Notification::assertSentTo($admin, UserSuspendedForAdminNotification::class);
    Notification::assertNotSentTo($user, UserSuspendedForAdminNotification::class);
});

it('is idempotent when the account is already suspended', function () {
    $user = makeVerifiedSuspensionUser();
    $user->forceFill([
        'status' => UserStatus::Suspended,
        'suspended_at' => now()->subDay(),
        'suspension_reason' => 'Original reason',
    ])->save();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'reason' => 'Replacement reason',
        'confirm' => '1',
    ])->assertRedirect(route('home'));

    expect($user->fresh()->suspension_reason)->toBe('Original reason')
        ->and(AdminAuditLog::query()->where('action', 'account.suspended')->count())->toBe(0);
    Notification::assertNothingSent();
});

it('does not allow a suspended user to log in', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Suspended,
        'email_verified_at' => now(),
    ]);
    $user->assignRole('user');

    $this->post(route('login.post'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('hides a suspended user profile', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Suspended,
        'is_profile_public' => true,
    ]);
    $user->assignRole('user');
    $viewer = makeVerifiedSuspensionUser();

    $this->actingAs($viewer)
        ->get(route('players.show', ['user' => $user->name]))
        ->assertNotFound();
});

it('rejects suspension without the current password', function () {
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'reason' => 'Taking a break',
        'confirm' => '1',
    ])->assertSessionHasErrors('password');

    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

it('rejects suspension without a reason', function () {
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'confirm' => '1',
    ])->assertSessionHasErrors('reason');

    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

it('does not create a deletion request when suspending an account', function () {
    $user = makeVerifiedSuspensionUser();

    $this->actingAs($user)->post(route('cabinet.danger.suspend'), [
        'password' => 'password',
        'reason' => 'Taking a break',
        'confirm' => '1',
    ]);

    $user->refresh();

    expect($user->status)->toBe(UserStatus::Suspended)
        ->and($user->deletion_requested_at)->toBeNull()
        ->and($user->deletion_reason)->toBeNull();
});
