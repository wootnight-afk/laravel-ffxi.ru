<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('translates activity type labels', function () {
    app()->setLocale('en');

    expect(ActivityType::Registered->label())->toBe('registered')
        ->and(ActivityType::NewsPublished->label())->toBe('published news')
        ->and(ActivityType::PhotoPublished->label())->toBe('published a photo')
        ->and(ActivityType::EventCreated->label())->toBe('created an event')
        ->and(ActivityType::EventJoined->label())->toBe('joined an event')
        ->and(ActivityType::CommentCreated->label())->toBe('left a comment')
        ->and(ActivityType::ChatMessage->label())->toBe('sent a chat message');

    app()->setLocale('ru');

    expect(ActivityType::Registered->label())->toBe('зарегистрировался')
        ->and(ActivityType::NewsPublished->label())->toBe('опубликовал новость')
        ->and(ActivityType::PhotoPublished->label())->toBe('опубликовал фото')
        ->and(ActivityType::EventCreated->label())->toBe('создал событие')
        ->and(ActivityType::EventJoined->label())->toBe('записался на событие')
        ->and(ActivityType::CommentCreated->label())->toBe('оставил комментарий')
        ->and(ActivityType::ChatMessage->label())->toBe('отправил сообщение в чат');
});

it('sets the Carbon locale from the request locale', function () {
    $this->withSession(['locale' => 'en'])->get(route('home'))->assertOk();
    expect(Carbon::getLocale())->toBe('en');

    $this->withSession(['locale' => 'ru'])->get(route('home'))->assertOk();
    expect(Carbon::getLocale())->toBe('ru');
});

it('renders relative activity times and labels in english', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00', 'UTC'));

    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->assignRole('user');

    Activity::create([
        'actor_id' => $viewer->id,
        'type' => ActivityType::Registered,
        'data' => ['name' => $viewer->name],
        'created_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($viewer)
        ->withSession(['locale' => 'en'])
        ->get(route('activity.index'))
        ->assertOk()
        ->assertSee('registered')
        ->assertSee('5 minutes ago')
        ->assertDontSee('зарегистрировался');
});
