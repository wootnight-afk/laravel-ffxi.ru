<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Notifications\AccountDeletionRequestedNotification;
use App\Notifications\UserDeletionRequestedForAdminNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function makeVerifiedDangerUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function makeDangerAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

beforeEach(function () {
    Cache::flush();
    Notification::fake();
});

// ------------------------------------------------------------------
// Access barriers
// ------------------------------------------------------------------

it('redirects guests from danger tab to login', function () {
    get(route('cabinet.tab', ['tab' => 'danger']))
        ->assertRedirect(route('login'));
});

it('redirects guests from danger request to login', function () {
    post(route('cabinet.danger.request'), [
        'password' => 'password',
        'reason' => 'test reason',
        'confirm' => '1',
    ])->assertRedirect(route('login'));
});

it('allows unverified user to read the danger tab', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    actingAs($user)
        ->get(route('cabinet.tab', ['tab' => 'danger']))
        ->assertOk();
});

it('blocks unverified user from submitting a deletion request', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    actingAs($user)
        ->post(route('cabinet.danger.request'), [
            'password' => 'password',
            'reason' => 'test reason',
            'confirm' => '1',
        ])
        ->assertRedirect(route('verification.notice'));

    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

it('renders the danger tab for a verified user', function () {
    $user = makeVerifiedDangerUser();

    actingAs($user)
        ->get(route('cabinet.tab', ['tab' => 'danger']))
        ->assertOk()
        ->assertSee('Опасная зона')
        ->assertSee('Запросить удаление аккаунта');
});

// ------------------------------------------------------------------
// Validation
// ------------------------------------------------------------------

it('rejects request with wrong password', function () {
    $user = makeVerifiedDangerUser();

    actingAs($user)
        ->post(route('cabinet.danger.request'), [
            'password' => 'not-the-real-one',
            'reason' => 'test reason',
            'confirm' => '1',
        ])
        ->assertSessionHasErrors('password');

    $user->refresh();
    expect($user->status)->toBe(UserStatus::Active);
    expect(AdminAuditLog::where('action', 'account.deletion_requested')->count())->toBe(0);
});

it('rejects request without reason', function () {
    $user = makeVerifiedDangerUser();

    actingAs($user)
        ->post(route('cabinet.danger.request'), [
            'password' => 'password',
            'confirm' => '1',
        ])
        ->assertSessionHasErrors('reason');

    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

it('rejects request with too short reason', function () {
    $user = makeVerifiedDangerUser();

    actingAs($user)
        ->post(route('cabinet.danger.request'), [
            'password' => 'password',
            'reason' => 'x',
            'confirm' => '1',
        ])
        ->assertSessionHasErrors('reason');
});

it('rejects request without confirm checkbox', function () {
    $user = makeVerifiedDangerUser();

    actingAs($user)
        ->post(route('cabinet.danger.request'), [
            'password' => 'password',
            'reason' => 'test reason',
        ])
        ->assertSessionHasErrors('confirm');

    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

// ------------------------------------------------------------------
// Successful request — full effect
// ------------------------------------------------------------------

it('sets status, reason and timestamp on deletion request', function () {
    $user = makeVerifiedDangerUser();

    actingAs($user)
        ->post(route('cabinet.danger.request'), [
            'password' => 'password',
            'reason' => 'Not playing anymore',
            'confirm' => '1',
        ])
        ->assertRedirect(route('home'));

    $user->refresh();
    expect($user->status)->toBe(UserStatus::DeletionRequested);
    expect($user->deletion_reason)->toBe('Not playing anymore');
    expect($user->deletion_requested_at)->not->toBeNull();
});

it('writes an audit entry without PII', function () {
    $user = makeVerifiedDangerUser();

    actingAs($user)->post(route('cabinet.danger.request'), [
        'password' => 'password',
        'reason' => 'test reason',
        'confirm' => '1',
    ]);

    $log = AdminAuditLog::where('action', 'account.deletion_requested')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->subject_id)->toBe($user->getKey());
    expect($log->user_id)->toBe($user->getKey());
    expect($log->old)->toHaveKey('status');
    expect($log->old['status'])->toBe('active');
    expect($log->new['status'])->toBe('deletion_requested');

    $json = json_encode($log->new);
    expect($json)->not->toContain('password');
    expect($json)->not->toContain('$2y$');
    expect($json)->not->toContain('test reason');
});

it('notifies user and admins', function () {
    $admin = makeDangerAdmin();
    $user = makeVerifiedDangerUser();

    actingAs($user)->post(route('cabinet.danger.request'), [
        'password' => 'password',
        'reason' => 'test reason',
        'confirm' => '1',
    ]);

    Notification::assertSentTo($user, AccountDeletionRequestedNotification::class);
    Notification::assertSentTo($admin, UserDeletionRequestedForAdminNotification::class);
});

it('does not notify admin when initiator is admin', function () {
    $admin = makeDangerAdmin();

    actingAs($admin)->post(route('cabinet.danger.request'), [
        'password' => 'password',
        'reason' => 'test reason',
        'confirm' => '1',
    ]);

    Notification::assertNotSentTo($admin, UserDeletionRequestedForAdminNotification::class);
    Notification::assertSentTo($admin, AccountDeletionRequestedNotification::class);
});

// ------------------------------------------------------------------
// Idempotency
// ------------------------------------------------------------------

it('is idempotent when the account is already in deletion_requested state', function () {
    $user = makeVerifiedDangerUser();

    // First request
    actingAs($user)->post(route('cabinet.danger.request'), [
        'password' => 'password',
        'reason' => 'first reason',
        'confirm' => '1',
    ]);

    $firstTimestamp = $user->fresh()->deletion_requested_at;

    // Second request — controller must short-circuit
    actingAs($user->fresh())->post(route('cabinet.danger.request'), [
        'password' => 'password',
        'reason' => 'second reason',
        'confirm' => '1',
    ])->assertRedirect(route('home'));

    $user->refresh();
    expect($user->deletion_reason)->toBe('first reason');
    expect($user->deletion_requested_at->equalTo($firstTimestamp))->toBeTrue();

    // Only one audit entry must exist.
    expect(AdminAuditLog::where('action', 'account.deletion_requested')
        ->where('subject_id', $user->getKey())
        ->count())->toBe(1);
});

// ------------------------------------------------------------------
// Rendering of deleted identity
// ------------------------------------------------------------------

it('renders deleted-account label for a deletion-requested user', function () {
    $user = User::factory()->create(['status' => UserStatus::DeletionRequested]);

    $html = Blade::render(
        '<x-user-identity :user="$user" />',
        ['user' => $user],
    );

    expect($html)->toContain('[аккаунт удалён]');
    expect($html)->not->toContain($user->name);
    expect($html)->not->toContain('href=');
});
