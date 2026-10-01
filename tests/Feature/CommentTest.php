<?php

use App\Models\Comment;
use App\Models\News;
use App\Models\User;
use App\Services\SettingsRepository;

function makeNews(User $author): News
{
    return News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Test news '.uniqid(),
        'slug' => 'test-news-'.uniqid(),
        'body' => 'Body text',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHour(),
        'comments_enabled' => true,
    ]);
}

function makeVerifiedUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

it('rejects unauthenticated comment posting', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $response = $this->postJson(route('comments.store', $news->slug), [
        'body' => 'This is a valid comment with enough characters.',
    ]);

    $response->assertUnauthorized();
});

it('rejects unverified user from posting comments', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $unverified = User::factory()->create(['email_verified_at' => null]);
    $unverified->assignRole('user');

    $response = $this->actingAs($unverified)->postJson(
        route('comments.store', $news->slug),
        ['body' => 'This is a valid comment with enough characters.']
    );

    $response->assertForbidden();
});

it('creates a pending comment under reputation mode', function () {
    app(SettingsRepository::class)->set('comments_moderation', 'reputation');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $user = makeVerifiedUser();

    $response = $this->actingAs($user)->postJson(
        route('comments.store', $news->slug),
        ['body' => 'This is a valid comment with enough characters.']
    );

    $response->assertCreated();
    expect(Comment::count())->toBe(1);
    expect(Comment::first()->status)->toBe(Comment::STATUS_PENDING);
});

it('auto-approves after five approved comments', function () {
    app(SettingsRepository::class)->set('comments_moderation', 'reputation');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $user = makeVerifiedUser();

    // Создаём 5 одобренных комментариев пользователя
    for ($i = 0; $i < 5; $i++) {
        Comment::create([
            'user_id' => $user->id,
            'commentable_type' => News::class,
            'commentable_id' => $news->id,
            'body' => 'Approved comment '.$i,
            'status' => Comment::STATUS_APPROVED,
        ]);
    }

    $response = $this->actingAs($user)->postJson(
        route('comments.store', $news->slug),
        ['body' => 'This is a valid comment with enough characters.']
    );

    $response->assertCreated();
    expect(Comment::where('body', 'This is a valid comment with enough characters.')->first()->status)
        ->toBe(Comment::STATUS_APPROVED);
});

it('auto-approves under "none" moderation', function () {
    app(SettingsRepository::class)->set('comments_moderation', 'none');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $user = makeVerifiedUser();

    $this->actingAs($user)->postJson(
        route('comments.store', $news->slug),
        ['body' => 'Auto-approved comment with enough characters.']
    )->assertCreated();

    expect(Comment::first()->status)->toBe(Comment::STATUS_APPROVED);
});

it('requires "all" mode to make everything pending', function () {
    app(SettingsRepository::class)->set('comments_moderation', 'all');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    // Даже модератор получает pending (правило действует на всех)
    $editor = User::factory()->create(['email_verified_at' => now()]);
    $editor->assignRole('editor');

    $this->actingAs($editor)->postJson(
        route('comments.store', $news->slug),
        ['body' => 'Editor comment with enough characters here.']
    )->assertCreated();

    expect(Comment::first()->status)->toBe(Comment::STATUS_PENDING);
});

it('rejects comment if news has comments disabled', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);
    $news->forceFill(['comments_enabled' => false])->save();

    $user = makeVerifiedUser();

    $this->actingAs($user)->postJson(
        route('comments.store', $news->slug),
        ['body' => 'Valid comment with enough characters here.']
    )->assertForbidden();
});

it('rejects comment shorter than 20 characters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $user = makeVerifiedUser();

    $this->actingAs($user)->postJson(
        route('comments.store', $news->slug),
        ['body' => 'Too short']
    )->assertJsonValidationErrors('body');
});

it('author can delete own comment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $user = makeVerifiedUser();
    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => News::class,
        'commentable_id' => $news->id,
        'body' => 'My own comment text with enough chars.',
        'status' => Comment::STATUS_APPROVED,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('comments.destroy', $comment))
        ->assertOk();

    expect(Comment::find($comment->id))->toBeNull();
});

it('moderator can delete any comment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $user = makeVerifiedUser();
    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => News::class,
        'commentable_id' => $news->id,
        'body' => 'Foreign comment text with enough chars.',
        'status' => Comment::STATUS_APPROVED,
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('comments.destroy', $comment))
        ->assertOk();
});

it('regular user cannot delete foreign comment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $userA = makeVerifiedUser();
    $userB = makeVerifiedUser();

    $comment = Comment::create([
        'user_id' => $userA->id,
        'commentable_type' => News::class,
        'commentable_id' => $news->id,
        'body' => 'Foreign comment text with enough chars.',
        'status' => Comment::STATUS_APPROVED,
    ]);

    $this->actingAs($userB)
        ->deleteJson(route('comments.destroy', $comment))
        ->assertForbidden();
});

it('user can report foreign comment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $userA = makeVerifiedUser();
    $userB = makeVerifiedUser();

    $comment = Comment::create([
        'user_id' => $userA->id,
        'commentable_type' => News::class,
        'commentable_id' => $news->id,
        'body' => 'Reportable comment text with enough chars.',
        'status' => Comment::STATUS_APPROVED,
    ]);

    $this->actingAs($userB)
        ->postJson(route('comments.report', $comment))
        ->assertOk();

    expect($comment->fresh()->is_reported)->toBeTrue();
});

it('user cannot report own comment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $news = makeNews($admin);

    $user = makeVerifiedUser();
    $comment = Comment::create([
        'user_id' => $user->id,
        'commentable_type' => News::class,
        'commentable_id' => $news->id,
        'body' => 'Own comment text with enough chars.',
        'status' => Comment::STATUS_APPROVED,
    ]);

    $this->actingAs($user)
        ->postJson(route('comments.report', $comment))
        ->assertForbidden();
});
