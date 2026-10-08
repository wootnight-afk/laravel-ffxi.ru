<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function makeMfaUser(string $role = 'user'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function mfaProvider(): AppAuthentication
{
    return AppAuthentication::make();
}

const MFA_TEST_SECRET = 'JBSWY3DPEHPK3PXP';

// ------------------------------------------------------------------
// Access barriers
// ------------------------------------------------------------------

it('redirects guests from every MFA route to login', function () {
    post(route('cabinet.security.mfa.setup'))->assertRedirect(route('login'));
    post(route('cabinet.security.mfa.confirm'), ['code' => '123456'])->assertRedirect(route('login'));
    post(route('cabinet.security.mfa.disable'), ['password' => 'password'])->assertRedirect(route('login'));
    post(route('cabinet.security.mfa.regenerate-codes'), ['password' => 'password'])->assertRedirect(route('login'));
    post(route('cabinet.security.mfa.cancel'))->assertRedirect(route('login'));
});

// ------------------------------------------------------------------
// View states
// ------------------------------------------------------------------

it('offers MFA setup when it is not configured', function () {
    $user = makeMfaUser();

    actingAs($user)
        ->get(route('cabinet.tab', ['tab' => 'security']))
        ->assertOk()
        ->assertSee('MFA не настроена')
        ->assertSee('Настроить MFA');
});

it('renders the QR code and the manual key while setup is pending', function () {
    $user = makeMfaUser();

    $this->actingAs($user)->withSession(['mfa_setup_secret' => MFA_TEST_SECRET]);

    get(route('cabinet.tab', ['tab' => 'security']))
        ->assertOk()
        ->assertSee('data:image/svg+xml;base64', false)
        ->assertSee(MFA_TEST_SECRET);
});

it('shows the enabled state and the remaining recovery code count', function () {
    $user = makeMfaUser();
    $user->saveAppAuthenticationSecret(MFA_TEST_SECRET);
    $user->saveAppAuthenticationRecoveryCodes([Hash::make('code-1'), Hash::make('code-2')]);

    actingAs($user)
        ->get(route('cabinet.tab', ['tab' => 'security']))
        ->assertOk()
        ->assertSee('MFA настроена')
        ->assertSee('Осталось резервных кодов');
});

// ------------------------------------------------------------------
// Setup + confirm
// ------------------------------------------------------------------

it('stores a pending secret in the session when setup starts', function () {
    $user = makeMfaUser();

    actingAs($user)
        ->post(route('cabinet.security.mfa.setup'))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']))
        ->assertSessionHas('mfa_setup_secret');

    expect(session('mfa_setup_secret'))->toBeString()->not->toBeEmpty();
    expect($user->refresh()->getAppAuthenticationSecret())->toBeNull();
});

it('enables MFA and flashes plaintext recovery codes on a valid code', function () {
    $user = makeMfaUser();

    $this->actingAs($user)->withSession(['mfa_setup_secret' => MFA_TEST_SECRET]);

    $response = post(route('cabinet.security.mfa.confirm'), [
        'code' => mfaProvider()->getCurrentCode($user, MFA_TEST_SECRET),
    ]);

    $response
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']))
        ->assertSessionHas('mfa_recovery_codes')
        ->assertSessionMissing('mfa_setup_secret');

    $user->refresh();
    expect($user->getAppAuthenticationSecret())->toBe(MFA_TEST_SECRET);

    $hashed = $user->getAppAuthenticationRecoveryCodes();
    expect($hashed)->toHaveCount(mfaProvider()->getRecoveryCodeCount());

    $plain = session('mfa_recovery_codes');
    expect($plain)->toHaveCount(mfaProvider()->getRecoveryCodeCount());

    // Stored values are hashes, never the plaintext codes.
    foreach ($plain as $code) {
        expect($hashed)->not->toContain($code);
    }

    expect(AdminAuditLog::where('action', 'mfa.enabled')->count())->toBe(1);
});

it('rejects an invalid code and keeps MFA disabled', function () {
    $user = makeMfaUser();

    $this->actingAs($user)->withSession(['mfa_setup_secret' => MFA_TEST_SECRET]);

    post(route('cabinet.security.mfa.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->getAppAuthenticationSecret())->toBeNull();
    expect(AdminAuditLog::where('action', 'mfa.enabled')->count())->toBe(0);
});

it('rejects a malformed code', function () {
    $user = makeMfaUser();

    $this->actingAs($user)->withSession(['mfa_setup_secret' => MFA_TEST_SECRET]);

    post(route('cabinet.security.mfa.confirm'), ['code' => 'abcd'])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->getAppAuthenticationSecret())->toBeNull();
});

it('refuses to confirm without a pending setup secret', function () {
    $user = makeMfaUser();

    actingAs($user)
        ->post(route('cabinet.security.mfa.confirm'), ['code' => '123456'])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->getAppAuthenticationSecret())->toBeNull();
});

it('refuses to start setup when MFA is already enabled', function () {
    $user = makeMfaUser();
    $user->saveAppAuthenticationSecret(MFA_TEST_SECRET);

    actingAs($user)
        ->post(route('cabinet.security.mfa.setup'))
        ->assertSessionHasErrors('mfa');

    expect($user->refresh()->getAppAuthenticationSecret())->toBe(MFA_TEST_SECRET);
});

// ------------------------------------------------------------------
// Disable
// ------------------------------------------------------------------

it('rejects disabling MFA with a wrong password', function () {
    $user = makeMfaUser();
    $user->saveAppAuthenticationSecret(MFA_TEST_SECRET);

    actingAs($user)
        ->post(route('cabinet.security.mfa.disable'), ['password' => 'not-the-real-one'])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->getAppAuthenticationSecret())->not->toBeNull();
    expect(AdminAuditLog::where('action', 'mfa.disabled')->count())->toBe(0);
});

it('disables MFA, clears secrets and audits the change', function () {
    $user = makeMfaUser();
    $user->saveAppAuthenticationSecret(MFA_TEST_SECRET);
    $user->saveAppAuthenticationRecoveryCodes([Hash::make('code-1')]);

    actingAs($user)
        ->post(route('cabinet.security.mfa.disable'), ['password' => 'password'])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']));

    $user->refresh();
    expect($user->getAppAuthenticationSecret())->toBeNull();
    expect($user->getAppAuthenticationRecoveryCodes())->toBeNull();

    $log = AdminAuditLog::where('action', 'mfa.disabled')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->subject_id)->toBe($user->getKey());
});

it('is idempotent when disabling MFA that is not configured', function () {
    $user = makeMfaUser();

    actingAs($user)
        ->post(route('cabinet.security.mfa.disable'), ['password' => 'password'])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']))
        ->assertSessionHasNoErrors();

    expect(AdminAuditLog::where('action', 'mfa.disabled')->count())->toBe(0);
});

// ------------------------------------------------------------------
// Recovery codes regeneration
// ------------------------------------------------------------------

it('regenerates recovery codes with the correct password', function () {
    $user = makeMfaUser();
    $user->saveAppAuthenticationSecret(MFA_TEST_SECRET);
    $user->saveAppAuthenticationRecoveryCodes([Hash::make('old-code')]);

    actingAs($user)
        ->post(route('cabinet.security.mfa.regenerate-codes'), ['password' => 'password'])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']))
        ->assertSessionHas('mfa_recovery_codes');

    $user->refresh();
    expect($user->getAppAuthenticationRecoveryCodes())->toHaveCount(mfaProvider()->getRecoveryCodeCount());

    // Regeneration must not enable/disable MFA and writes no audit entry.
    expect($user->getAppAuthenticationSecret())->toBe(MFA_TEST_SECRET);
    expect(AdminAuditLog::whereIn('action', ['mfa.enabled', 'mfa.disabled'])->count())->toBe(0);
});

it('rejects recovery code regeneration with a wrong password', function () {
    $user = makeMfaUser();
    $original = Hash::make('old-code');
    $user->saveAppAuthenticationSecret(MFA_TEST_SECRET);
    $user->saveAppAuthenticationRecoveryCodes([$original]);

    actingAs($user)
        ->post(route('cabinet.security.mfa.regenerate-codes'), ['password' => 'not-the-real-one'])
        ->assertSessionHasErrors('password');

    $codes = $user->refresh()->getAppAuthenticationRecoveryCodes();
    expect($codes)->toBe([$original])
        ->and(Hash::check('old-code', $codes[0]))->toBeTrue();
});

it('refuses to regenerate recovery codes when MFA is not configured', function () {
    $user = makeMfaUser();

    actingAs($user)
        ->post(route('cabinet.security.mfa.regenerate-codes'), ['password' => 'password'])
        ->assertSessionHasErrors('password');
});

// ------------------------------------------------------------------
// Cancel
// ------------------------------------------------------------------

it('clears the pending secret on cancel', function () {
    $user = makeMfaUser();

    $this->actingAs($user)->withSession(['mfa_setup_secret' => MFA_TEST_SECRET]);

    post(route('cabinet.security.mfa.cancel'))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']))
        ->assertSessionMissing('mfa_setup_secret');
});

// ------------------------------------------------------------------
// Ownership + secret hygiene
// ------------------------------------------------------------------

it('acts only on the authenticated user regardless of submitted ids', function () {
    $actor = makeMfaUser();
    $victim = makeMfaUser();

    $this->actingAs($actor)->withSession(['mfa_setup_secret' => MFA_TEST_SECRET]);

    post(route('cabinet.security.mfa.confirm'), [
        'code' => mfaProvider()->getCurrentCode($actor, MFA_TEST_SECRET),
        'user_id' => $victim->getKey(),
    ])->assertRedirect(route('cabinet.tab', ['tab' => 'security']));

    expect($actor->refresh()->getAppAuthenticationSecret())->toBe(MFA_TEST_SECRET);
    expect($victim->refresh()->getAppAuthenticationSecret())->toBeNull();
});

it('never writes secrets or recovery codes to the audit log', function () {
    $user = makeMfaUser();

    $this->actingAs($user)->withSession(['mfa_setup_secret' => MFA_TEST_SECRET]);

    post(route('cabinet.security.mfa.confirm'), [
        'code' => mfaProvider()->getCurrentCode($user, MFA_TEST_SECRET),
    ]);

    $log = AdminAuditLog::where('action', 'mfa.enabled')->latest('id')->first();
    expect($log)->not->toBeNull();

    $payload = json_encode([$log->old, $log->new]);
    expect($payload)->not->toContain(MFA_TEST_SECRET);

    foreach (session('mfa_recovery_codes') as $code) {
        expect($payload)->not->toContain($code);
    }
});

it('lets an admin manage their own MFA', function () {
    $admin = makeMfaUser('admin');

    actingAs($admin)
        ->post(route('cabinet.security.mfa.setup'))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'security']))
        ->assertSessionHas('mfa_setup_secret');
});
