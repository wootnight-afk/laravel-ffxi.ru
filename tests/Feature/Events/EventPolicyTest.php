<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

function d21PolicyUser(string $role = 'user', bool $verified = true): User
{
    $user = User::factory()->create([
        'email_verified_at' => $verified ? now() : null,
    ]);
    $user->assignRole($role);

    return $user;
}

function d21PolicyEvent(User $leader, EventStatus $status = EventStatus::Planned): Event
{
    return Event::create([
        'user_id' => $leader->id,
        'title' => 'Policy test event',
        'description' => 'A sufficiently long event description.',
        'starts_at' => now()->addDay(),
        'status' => $status,
    ]);
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows viewAny and view without duplicating section middleware checks', function () {
    $user = d21PolicyUser();
    $event = d21PolicyEvent($user);

    expect(Gate::forUser($user)->allows('viewAny', Event::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $event))->toBeTrue();
});

it('allows event creation with verified email and permission', function () {
    $user = d21PolicyUser();

    expect(Gate::forUser($user)->allows('create', Event::class))->toBeTrue();
});

it('denies event creation without permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    expect(Gate::forUser($user)->allows('create', Event::class))->toBeFalse();
});

it('denies event creation for unverified users', function () {
    $user = d21PolicyUser(verified: false);

    expect(Gate::forUser($user)->allows('create', Event::class))->toBeFalse();
});

it('allows the leader to update a planned event', function () {
    $leader = d21PolicyUser();
    $event = d21PolicyEvent($leader);

    expect(Gate::forUser($leader)->allows('update', $event))->toBeTrue();
});

it('denies another user without manageAny from updating the event', function () {
    $leader = d21PolicyUser();
    $other = d21PolicyUser();
    $event = d21PolicyEvent($leader);

    expect(Gate::forUser($other)->allows('update', $event))->toBeFalse();
});

it('denies updates to cancelled and completed events', function (EventStatus $status) {
    $leader = d21PolicyUser();
    $event = d21PolicyEvent($leader, $status);

    expect(Gate::forUser($leader)->allows('update', $event))->toBeFalse();
})->with([
    'cancelled' => [EventStatus::Cancelled],
    'completed' => [EventStatus::Completed],
]);

it('allows policy update for a planned event whose start time has passed', function () {
    $leader = d21PolicyUser();
    $event = d21PolicyEvent($leader);
    $event->forceFill(['starts_at' => now()->subMinute()])->save();

    expect(Gate::forUser($leader)->allows('update', $event))->toBeTrue();
});

it('allows the leader to cancel a planned event', function () {
    $leader = d21PolicyUser();
    $event = d21PolicyEvent($leader);

    expect(Gate::forUser($leader)->allows('cancel', $event))->toBeTrue();
});

it('denies a non-manager from cancelling another users event', function () {
    $leader = d21PolicyUser();
    $other = d21PolicyUser();
    $event = d21PolicyEvent($leader);

    expect(Gate::forUser($other)->allows('cancel', $event))->toBeFalse();
});

it('allows joining with verified email and permission', function () {
    $user = d21PolicyUser();
    $event = d21PolicyEvent(d21PolicyUser());

    expect(Gate::forUser($user)->allows('join', $event))->toBeTrue();
});

it('denies joining for unverified users', function () {
    $user = d21PolicyUser(verified: false);
    $event = d21PolicyEvent(d21PolicyUser());

    expect(Gate::forUser($user)->allows('join', $event))->toBeFalse();
});

it('allows a verified user to leave without checking event state', function (EventStatus $status) {
    $user = d21PolicyUser();
    $event = d21PolicyEvent(d21PolicyUser(), $status);
    $event->forceFill(['starts_at' => now()->subDay()])->save();

    expect(Gate::forUser($user)->allows('leave', $event))->toBeTrue();
})->with([
    'planned' => [EventStatus::Planned],
    'cancelled' => [EventStatus::Cancelled],
    'completed' => [EventStatus::Completed],
]);

it('denies leave for an unverified user even after they joined', function () {
    $user = d21PolicyUser(verified: false);
    $event = d21PolicyEvent(d21PolicyUser());

    expect(Gate::forUser($user)->allows('leave', $event))->toBeFalse();
});

it('grants manageAny to editors but not ordinary users', function () {
    $policy = app(EventPolicy::class);
    $editor = d21PolicyUser('editor');
    $user = d21PolicyUser();

    expect($policy->manageAny($editor))->toBeTrue()
        ->and($policy->manageAny($user))->toBeFalse();
});
