<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mfa\MfaPolicy;
use App\Services\SettingsRepository;

/**
 * @param  array<string, bool>  $values
 */
function mfaPolicyStub(array $values): SettingsRepository
{
    return new class($values) extends SettingsRepository
    {
        /**
         * @param  array<string, bool>  $values
         */
        public function __construct(private readonly array $values) {}

        public function bool(string $key, bool $default = false): bool
        {
            return array_key_exists($key, $this->values)
                ? (bool) $this->values[$key]
                : $default;
        }
    };
}

function mfaPolicyUser(bool $isAdmin): User
{
    $user = Mockery::mock(User::class);
    $user->shouldReceive('hasRole')->with('admin')->andReturn($isAdmin);

    return $user;
}

beforeEach(function () {
    $this->originalEnvironment = app()->environment();
});

afterEach(function () {
    app()->detectEnvironment(fn () => $this->originalEnvironment);
});

// ------------------------------------------------------------------
// Production — fail-closed
// ------------------------------------------------------------------

it('forces global MFA on in production regardless of the setting', function () {
    app()->detectEnvironment(fn () => 'production');

    $policy = new MfaPolicy(mfaPolicyStub(['mfa_global_enabled' => false]));

    expect($policy->globalEnabled())->toBeTrue();
});

it('forces admin MFA on in production regardless of the setting', function () {
    app()->detectEnvironment(fn () => 'production');

    $policy = new MfaPolicy(mfaPolicyStub(['admin_2fa_required' => false]));

    expect($policy->adminMfaRequired(mfaPolicyUser(true)))->toBeTrue();
});

it('keeps admin MFA on in production when the setting is already on', function () {
    app()->detectEnvironment(fn () => 'production');

    $policy = new MfaPolicy(mfaPolicyStub(['admin_2fa_required' => true]));

    expect($policy->adminMfaRequired(mfaPolicyUser(true)))->toBeTrue();
});

it('never requires MFA from non-admins in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $policy = new MfaPolicy(mfaPolicyStub(['admin_2fa_required' => true]));

    expect($policy->adminMfaRequired(mfaPolicyUser(false)))->toBeFalse();
});

// ------------------------------------------------------------------
// Non-production — database settings are authoritative
// ------------------------------------------------------------------

it('follows the database setting for global MFA outside production', function () {
    app()->detectEnvironment(fn () => 'local');

    $off = new MfaPolicy(mfaPolicyStub(['mfa_global_enabled' => false]));
    $on = new MfaPolicy(mfaPolicyStub(['mfa_global_enabled' => true]));

    expect($off->globalEnabled())->toBeFalse()
        ->and($on->globalEnabled())->toBeTrue();
});

it('defaults global MFA to on outside production when the setting is missing', function () {
    app()->detectEnvironment(fn () => 'local');

    $policy = new MfaPolicy(mfaPolicyStub([]));

    expect($policy->globalEnabled())->toBeTrue();
});

it('follows the database setting for admin MFA outside production', function () {
    app()->detectEnvironment(fn () => 'local');

    $off = new MfaPolicy(mfaPolicyStub(['admin_2fa_required' => false]));
    $on = new MfaPolicy(mfaPolicyStub(['admin_2fa_required' => true]));

    expect($off->adminMfaRequired(mfaPolicyUser(true)))->toBeFalse()
        ->and($on->adminMfaRequired(mfaPolicyUser(true)))->toBeTrue();
});

it('never requires MFA from non-admins outside production', function () {
    app()->detectEnvironment(fn () => 'local');

    $policy = new MfaPolicy(mfaPolicyStub(['admin_2fa_required' => true]));

    expect($policy->adminMfaRequired(mfaPolicyUser(false)))->toBeFalse();
});
