<?php

declare(strict_types=1);

use App\Http\Middleware\RequireMfa;
use App\Models\AdminAuditLog;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

const CHALLENGE_SECRET = 'KRSXG5CTMVRXEZLU';

function challengeUser(string $role = 'user', bool $withMfa = true): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    if ($withMfa) {
        $user->saveAppAuthenticationSecret(CHALLENGE_SECRET);
    }

    return $user->refresh();
}

beforeEach(function () {
    Cache::flush();
});

// ------------------------------------------------------------------
// GET /mfa/challenge
// ------------------------------------------------------------------

it('redirects guests from the challenge to login', function () {
    get(route('mfa.challenge'))->assertRedirect(route('login'));
});

it('redirects home when MFA is not configured', function () {
    $user = challengeUser(withMfa: false);

    actingAs($user)->get(route('mfa.challenge'))->assertRedirect(route('home'));
});

it('renders the challenge form for an unverified user', function () {
    $user = challengeUser();

    actingAs($user)
        ->get(route('mfa.challenge'))
        ->assertOk()
        ->assertSee('Подтверждение входа')
        ->assertSee('Резервный код');
});

it('redirects an already verified user away from the challenge', function () {
    $user = challengeUser();

    actingAs($user)
        ->withSession([RequireMfa::SESSION_USER_ID_KEY => $user->getKey()])
        ->get(route('mfa.challenge'))
        ->assertRedirect(route('home'));
});

// ------------------------------------------------------------------
// POST /mfa/challenge — TOTP
// ------------------------------------------------------------------

it('accepts a valid TOTP code and stores the verification', function () {
    $user = challengeUser();

    $code = AppAuthentication::make()->getCurrentCode($user, CHALLENGE_SECRET);

    actingAs($user)
        ->post(route('mfa.challenge.verify'), ['code' => $code])
        ->assertRedirect(route('home'))
        ->assertSessionHas(RequireMfa::SESSION_USER_ID_KEY, $user->getKey());

    expect(AdminAuditLog::where('action', 'mfa.challenge_success')->count())->toBe(1);
});

it('redirects to the intended URL after a successful challenge', function () {
    $user = challengeUser();

    $code = AppAuthentication::make()->getCurrentCode($user, CHALLENGE_SECRET);

    actingAs($user)
        ->withSession(['url.intended' => '/players'])
        ->post(route('mfa.challenge.verify'), ['code' => $code])
        ->assertRedirect('/players');
});

it('rejects an invalid TOTP code without verifying', function () {
    $user = challengeUser();

    actingAs($user)
        ->post(route('mfa.challenge.verify'), ['code' => '000000'])
        ->assertSessionHasErrors('code')
        ->assertSessionMissing(RequireMfa::SESSION_USER_ID_KEY);

    expect(AdminAuditLog::where('action', 'mfa.challenge_failure')->count())->toBe(1);
});

it('rejects a malformed TOTP code', function () {
    $user = challengeUser();

    actingAs($user)
        ->post(route('mfa.challenge.verify'), ['code' => 'abc'])
        ->assertSessionHasErrors('code');

    expect(AdminAuditLog::where('action', 'mfa.challenge_success')->count())->toBe(0);
});

// ------------------------------------------------------------------
// POST /mfa/challenge — recovery code
// ------------------------------------------------------------------

it('accepts a valid recovery code and consumes it', function () {
    $user = challengeUser();
    $user->saveAppAuthenticationRecoveryCodes([
        Hash::make('recovery-one'),
        Hash::make('recovery-two'),
    ]);

    actingAs($user)
        ->post(route('mfa.challenge.verify'), [
            'use_recovery_code' => '1',
            'recovery_code' => 'recovery-one',
        ])
        ->assertRedirect(route('home'))
        ->assertSessionHas(RequireMfa::SESSION_USER_ID_KEY, $user->getKey());

    expect($user->refresh()->getAppAuthenticationRecoveryCodes())->toHaveCount(1);
    expect(AdminAuditLog::where('action', 'mfa.challenge_success')->count())->toBe(1);
});

it('rejects an invalid recovery code', function () {
    $user = challengeUser();
    $user->saveAppAuthenticationRecoveryCodes([Hash::make('recovery-one')]);

    actingAs($user)
        ->post(route('mfa.challenge.verify'), [
            'use_recovery_code' => '1',
            'recovery_code' => 'nope',
        ])
        ->assertSessionHasErrors('recovery_code')
        ->assertSessionMissing(RequireMfa::SESSION_USER_ID_KEY);
});

it('requires a recovery code when the toggle is on', function () {
    $user = challengeUser();

    actingAs($user)
        ->post(route('mfa.challenge.verify'), ['use_recovery_code' => '1'])
        ->assertSessionHasErrors('recovery_code');
});

// ------------------------------------------------------------------
// Rate limiting
// ------------------------------------------------------------------

it('rate limits repeated challenge attempts', function () {
    $user = challengeUser();

    for ($i = 0; $i < 5; $i++) {
        actingAs($user)
            ->post(route('mfa.challenge.verify'), ['code' => '000000'])
            ->assertRedirect();
    }

    actingAs($user)
        ->post(route('mfa.challenge.verify'), ['code' => '000000'])
        ->assertStatus(429);
});

// ------------------------------------------------------------------
// Secret hygiene
// ------------------------------------------------------------------

it('never writes the secret or the submitted code to the audit log', function () {
    $user = challengeUser();

    $code = AppAuthentication::make()->getCurrentCode($user, CHALLENGE_SECRET);

    actingAs($user)->post(route('mfa.challenge.verify'), ['code' => $code]);

    $log = AdminAuditLog::where('action', 'mfa.challenge_success')->latest('id')->first();
    $payload = json_encode([$log->old, $log->new]);

    expect($payload)->not->toContain(CHALLENGE_SECRET)
        ->and($payload)->not->toContain($code);
});
