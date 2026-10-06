<?php

declare(strict_types=1);

use App\Models\RestoreRequest;
use App\Models\User;
use App\Services\Backup\RestoreRequestService;

function restoreRequestAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

it('creates a pending request with an HMAC token and a 30-minute expiry', function () {
    $admin = restoreRequestAdmin();
    $service = app(RestoreRequestService::class);

    $request = $service->create('11111111-1111-1111-1111-111111111111', (int) $admin->getKey());

    $expectedExpiry = now()->addMinutes(30);

    expect($request->status)->toBe(RestoreRequest::STATUS_PENDING)
        ->and($request->consumed_at)->toBeNull()
        ->and($request->token)->toHaveLength(64)
        ->and($request->expires_at->between($expectedExpiry->copy()->subMinute(), $expectedExpiry->copy()->addMinute()))->toBeTrue()
        ->and($service->tokenMatches($request))->toBeTrue();
});

it('persists the request so the CLI can find it by id', function () {
    $admin = restoreRequestAdmin();
    $service = app(RestoreRequestService::class);

    $request = $service->create('22222222-2222-2222-2222-222222222222', (int) $admin->getKey());

    expect($service->find($request->id)?->id)->toBe($request->id)
        ->and($service->find('00000000-0000-0000-0000-000000000000'))->toBeNull();
});

it('detects a tampered token', function () {
    $admin = restoreRequestAdmin();
    $service = app(RestoreRequestService::class);

    $request = $service->create('33333333-3333-3333-3333-333333333333', (int) $admin->getKey());
    $request->forceFill(['token' => str_repeat('0', 64)])->save();

    expect($service->tokenMatches($request->fresh()))->toBeFalse();
});

it('detects tampering with the signed fields', function () {
    $admin = restoreRequestAdmin();
    $service = app(RestoreRequestService::class);

    $request = $service->create('44444444-4444-4444-4444-444444444444', (int) $admin->getKey());
    $request->forceFill(['backup_id' => '99999999-9999-9999-9999-999999999999'])->save();

    expect($service->tokenMatches($request->fresh()))->toBeFalse();
});

it('marks the request expired after 30 minutes', function () {
    $admin = restoreRequestAdmin();
    $request = app(RestoreRequestService::class)
        ->create('55555555-5555-5555-5555-555555555555', (int) $admin->getKey());

    expect($request->isUsable())->toBeTrue();

    $this->travel(31)->minutes();

    expect($request->fresh()->isExpired())->toBeTrue()
        ->and($request->fresh()->isUsable())->toBeFalse();
});
