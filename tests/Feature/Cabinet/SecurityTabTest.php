<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Notifications\EmailChangedOldAddressNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\UserEmailChangedForAdminNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function makeVerifiedSecurityUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function makeSecurityAdmin(): User
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

it('redirects guests from security tab to login', function () {
    get(route('cabinet.tab', ['tab' => 'security']))
        ->assertRedirect(route('login'));

    post(route('cabinet.security.password'), [
        'current_password' => 'x',
        'password' => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
    ])->assertRedirect(route('login'));

    post(route('cabinet.security.email'), [
        'current_password' => 'x',
        'email' => 'new@example.com',
    ])->assertRedirect(route('login'));
});

it('allows unverified user to read the security tab', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    actingAs($user)
        ->get(route('cabinet.tab', ['tab' => 'security']))
        ->assertOk();
});

it('blocks unverified user from password change', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    actingAs($user)
        ->post(route('cabinet.security.password'), [
            'current_password' => 'password',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ])
        ->assertRedirect(route('verification.notice'));
});

it('blocks unverified user from email change', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    actingAs($user)
        ->post(route('cabinet.security.email'), [
            'current_password' => 'password',
            'email' => 'new@example.com',
        ])
        ->assertRedirect(route('verification.notice'));
});

it('renders the security tab for a verified user', function () {
    $user = makeVerifiedSecurityUser();

    actingAs($user)
        ->get(route('cabinet.tab', ['tab' => 'security']))
        ->assertOk()
        ->assertSee('Безопасность')
        ->assertSee('Email', false)
        ->assertSee('Пароль', false);
});

// ------------------------------------------------------------------
// Password change — validation
// ------------------------------------------------------------------

it('rejects password change with wrong current password', function () {
    $user = makeVerifiedSecurityUser();

    actingAs($user)
        ->post(route('cabinet.security.password'), [
            'current_password' => 'not-the-real-one',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(AdminAuditLog::where('action', 'password.changed')->count())->toBe(0);
});

it('rejects password change with mismatched confirmation', function () {
    $user = makeVerifiedSecurityUser();

    actingAs($user)
        ->post(route('cabinet.security.password'), [
            'current_password' => 'password',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass456',
        ])
        ->assertSessionHasErrors('password');

    expect(AdminAuditLog::where('action', 'password.changed')->count())->toBe(0);
});

// ------------------------------------------------------------------
// Password change — success
// ------------------------------------------------------------------

it('changes password and notifies user', function () {
    $user = makeVerifiedSecurityUser();
    $oldHash = $user->password;

    actingAs($user)
        ->post(route('cabinet.security.password'), [
            'current_password' => 'password',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']));

    $user->refresh();
    expect($user->password)->not->toBe($oldHash);
    expect(password_verify('StrongPass123', $user->password))->toBeTrue();

    Notification::assertSentTo($user, PasswordChangedNotification::class);

    $log = AdminAuditLog::where('action', 'password.changed')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->subject_id)->toBe($user->getKey());
    expect($log->user_id)->toBe($user->getKey());
});

it('does not store password or hash in audit entry', function () {
    $user = makeVerifiedSecurityUser();

    actingAs($user)->post(route('cabinet.security.password'), [
        'current_password' => 'password',
        'password' => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
    ]);

    $log = AdminAuditLog::where('action', 'password.changed')->latest('id')->first();
    expect($log)->not->toBeNull();

    $newJson = json_encode($log->new);
    $oldJson = json_encode($log->old);

    expect($log->new)->not->toHaveKey('password');
    expect($log->new)->not->toHaveKey('password_hash');
    expect($log->new)->not->toHaveKey('hash');
    expect($newJson)->not->toContain('$2y$');
    expect($newJson)->not->toContain('StrongPass123');
    expect($oldJson)->not->toContain('$2y$');
    expect($oldJson)->not->toContain('password');
});

// ------------------------------------------------------------------
// Email change — validation
// ------------------------------------------------------------------

it('rejects email change with wrong current password', function () {
    $user = makeVerifiedSecurityUser();

    actingAs($user)
        ->post(route('cabinet.security.email'), [
            'current_password' => 'not-the-real-one',
            'email' => 'new@example.com',
        ])
        ->assertSessionHasErrors('current_password');

    $user->refresh();
    expect($user->email)->not->toBe('new@example.com');
    expect(AdminAuditLog::where('action', 'email.changed')->count())->toBe(0);
});

it('rejects email change with duplicate email', function () {
    // Reserve the target email first.
    User::factory()->create(['email' => 'taken@example.com']);

    $user = makeVerifiedSecurityUser();

    actingAs($user)
        ->post(route('cabinet.security.email'), [
            'current_password' => 'password',
            'email' => 'taken@example.com',
        ])
        ->assertSessionHasErrors('email');

    $user->refresh();
    expect($user->email)->not->toBe('taken@example.com');
    expect(AdminAuditLog::where('action', 'email.changed')->count())->toBe(0);
});

// ------------------------------------------------------------------
// Email change — success
// ------------------------------------------------------------------

it('changes email and clears verification', function () {
    $user = makeVerifiedSecurityUser();
    $oldEmail = $user->email;

    actingAs($user)
        ->post(route('cabinet.security.email'), [
            'current_password' => 'password',
            'email' => 'newmail@example.com',
        ])
        ->assertRedirect(route('verification.notice'));

    $user->refresh();
    expect($user->email)->toBe('newmail@example.com');
    expect($user->email_verified_at)->toBeNull();

    $log = AdminAuditLog::where('action', 'email.changed')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->old)->toHaveKey('email');
    expect($log->old['email'])->toBe($oldEmail);
    expect($log->new)->toHaveKey('email');
    expect($log->new['email'])->toBe('newmail@example.com');
});

it('sends verification, old-address, and admin notifications', function () {
    $admin = makeSecurityAdmin();
    $user = makeVerifiedSecurityUser();
    $oldEmail = $user->email;

    actingAs($user)->post(route('cabinet.security.email'), [
        'current_password' => 'password',
        'email' => 'newmail@example.com',
    ]);

    Notification::assertSentTo($user, VerifyEmail::class);
    Notification::assertSentOnDemand(
        EmailChangedOldAddressNotification::class,
        function ($notification, $channels, $notifiable) use ($oldEmail) {
            return $notifiable->routes['mail'] === $oldEmail;
        },
    );
    Notification::assertSentTo($admin, UserEmailChangedForAdminNotification::class);
});

it('does not notify admin when initiator is admin', function () {
    $admin = makeSecurityAdmin();

    actingAs($admin)->post(route('cabinet.security.email'), [
        'current_password' => 'password',
        'email' => 'admin-new@example.com',
    ]);

    Notification::assertNotSentTo($admin, UserEmailChangedForAdminNotification::class);
    Notification::assertSentTo($admin, VerifyEmail::class);
});

it('blocks write routes after email change', function () {
    $user = makeVerifiedSecurityUser();

    actingAs($user)->post(route('cabinet.security.email'), [
        'current_password' => 'password',
        'email' => 'newmail@example.com',
    ]);

    actingAs($user->fresh())
        ->post(route('cabinet.social.store'), [
            'type' => 'other',
            'url' => 'https://example.com/profile',
        ])
        ->assertRedirect(route('verification.notice'));
});
