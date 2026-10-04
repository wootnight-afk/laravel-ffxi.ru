<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Services\ActivityFeedGrouper;

function d3GroupingActivity(int $id, ActivityType $type, ?int $actorId): Activity
{
    $activity = new Activity;
    $activity->forceFill([
        'id' => $id,
        'type' => $type,
        'actor_id' => $actorId,
        'data' => [],
        'created_at' => now(),
    ]);

    return $activity;
}

it('groups neighboring activities with the same actor and type only', function () {
    $activities = [
        d3GroupingActivity(1, ActivityType::Registered, 5),
        d3GroupingActivity(2, ActivityType::Registered, 5),
        d3GroupingActivity(3, ActivityType::Registered, 8),
        d3GroupingActivity(4, ActivityType::NewsPublished, 5),
        d3GroupingActivity(5, ActivityType::Registered, 5),
    ];

    $groups = app(ActivityFeedGrouper::class)->group($activities);

    expect(array_map(fn (array $group): array => array_map(
        fn (Activity $activity): int => $activity->id,
        $group['items'],
    ), $groups))->toBe([[1, 2], [3], [4], [5]]);
});

it('keeps a singleton group intact', function () {
    $activity = d3GroupingActivity(1, ActivityType::EventCreated, 2);

    expect(app(ActivityFeedGrouper::class)->group([$activity]))->toBe([
        ['activity' => $activity, 'items' => [$activity]],
    ]);
});

it('sorts recent activity deterministically by created_at and id', function () {
    expect(
        Activity::query()->recent()->toSql(),
    )->toContain('order by `created_at` desc, `id` desc');
});
