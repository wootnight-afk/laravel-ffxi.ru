<?php

use App\Enums\ActivityType;
use App\Enums\EventStatus;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ActivitySubjectResolver;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('does not build a subject link for a guest viewer', function () {
    $actor = User::factory()->create();
    $event = Event::create([
        'user_id' => $actor->id,
        'title' => 'Resolver event',
        'description' => 'Description for the resolver event.',
        'starts_at' => now()->addDay(),
        'status' => EventStatus::Planned,
    ]);
    $activity = app(ActivityLogger::class)->log(ActivityType::EventCreated, $actor, $event);

    expect(app(ActivitySubjectResolver::class)->url($activity, null))->toBeNull();
});

it('does not link to an unavailable or missing subject', function () {
    $actor = User::factory()->create();
    $activity = new Activity([
        'actor_id' => $actor->id,
        'type' => ActivityType::EventCreated,
        'subject_type' => Event::class,
        'subject_id' => 999999,
        'data' => ['title' => 'Missing event'],
        'created_at' => now(),
    ]);

    expect(app(ActivitySubjectResolver::class)->url($activity, $actor))->toBeNull();
});
