<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Carbon;

function d7Activity(Carbon $createdAt): Activity
{
    return Activity::create([
        'actor_id' => User::factory()->create()->id,
        'type' => ActivityType::Registered,
        'data' => [],
        'created_at' => $createdAt,
    ]);
}

it('deletes activities older than the configured retention period', function () {
    $expired = d7Activity(now()->subDays(181));
    $fresh = d7Activity(now()->subDays(179));

    $this->artisan('app:cleanup-activities')->assertExitCode(0);

    expect(Activity::query()->find($expired->id))->toBeNull()
        ->and(Activity::query()->find($fresh->id))->not->toBeNull();
});

it('does not delete fresh activities', function () {
    $fresh = d7Activity(now()->subDay());

    $this->artisan('app:cleanup-activities')->assertExitCode(0);

    expect(Activity::query()->find($fresh->id))->not->toBeNull();
});

it('uses activity_retention_days from settings', function () {
    app(SettingsRepository::class)->set('activity_retention_days', 5);
    $expired = d7Activity(now()->subDays(6));
    $fresh = d7Activity(now()->subDays(4));

    $this->artisan('app:cleanup-activities')->assertExitCode(0);

    expect(Activity::query()->find($expired->id))->toBeNull()
        ->and(Activity::query()->find($fresh->id))->not->toBeNull();
});
