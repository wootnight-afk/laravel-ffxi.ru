<?php

use App\Enums\EventParticipantStatus;
use App\Enums\EventStatus;
use App\Models\Comment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

function d24User(string $role = 'user', bool $verified = true): User
{
    $user = User::factory()->create([
        'email_verified_at' => $verified ? now() : null,
    ]);
    $user->assignRole($role);

    return $user;
}

function d24Event(User $leader, string $title = 'Comment target'): Event
{
    $type = EventType::create([
        'key' => 'comment-'.strtolower(str_replace(' ', '-', $title)),
        'title' => 'Community',
        'is_active' => true,
    ]);

    $event = Event::create([
        'user_id' => $leader->id,
        'type_id' => $type->id,
        'title' => $title,
        'description' => 'An event for testing comment behavior.',
        'starts_at' => now()->addDays(2),
        'status' => EventStatus::Planned,
    ]);
    EventParticipant::create([
        'event_id' => $event->id,
        'user_id' => $leader->id,
        'status' => EventParticipantStatus::Joined,
        'joined_at' => now(),
        'left_at' => null,
    ]);

    return $event;
}

function d24Comment(Event $event, User $user, array $overrides = []): Comment
{
    return Comment::create(array_merge([
        'user_id' => $user->id,
        'commentable_type' => Event::class,
        'commentable_id' => $event->id,
        'parent_id' => null,
        'body' => 'A comment body with sufficient length.',
        'status' => Comment::STATUS_APPROVED,
    ], $overrides));
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('stores comments against the explicitly bound event using global moderation', function () {
    $author = d24User();
    $event = d24Event(d24User());
    app(SettingsRepository::class)->set('comments_moderation', 'all');

    $response = $this->actingAs($author)->postJson(route('events.comments.store', $event), [
        'body' => 'This event deserves a coordinated group.',
    ]);

    $response->assertCreated()->assertJsonPath('status', Comment::STATUS_PENDING);
    $comment = Comment::query()->findOrFail($response->json('id'));

    expect($comment->commentable_type)->toBe(Event::class)
        ->and($comment->commentable_id)->toBe($event->id)
        ->and($comment->user_id)->toBe($author->id);
});

it('uses none mode and escapes approved event comments in the page', function () {
    $author = d24User();
    $event = d24Event(d24User());
    app(SettingsRepository::class)->set('comments_moderation', 'none');

    $this->actingAs($author)->postJson(route('events.comments.store', $event), [
        'body' => '<script>alert(1)</script>Safe event comment.',
    ])->assertCreated();

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;Safe event comment.', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('applies reputation moderation using the users approved comments globally', function () {
    $author = d24User();
    $event = d24Event(d24User());
    $otherEvent = d24Event(d24User(), 'Other comment target');
    app(SettingsRepository::class)->set('comments_moderation', 'reputation');

    foreach (range(1, 5) as $index) {
        d24Comment($otherEvent, $author, ['body' => "Approved comment number {$index}."]);
    }

    $this->actingAs($author)->postJson(route('events.comments.store', $event), [
        'body' => 'The sixth approved comment for this author.',
    ])->assertCreated()->assertJsonPath('status', Comment::STATUS_APPROVED);
});

it('allows replies only to root comments on the same event', function () {
    $author = d24User();
    $event = d24Event(d24User());
    $root = d24Comment($event, d24User());

    $response = $this->actingAs($author)->postJson(route('events.comments.store', $event), [
        'body' => 'A reply to the root event comment.',
        'parent_id' => $root->id,
    ]);

    $response->assertCreated();
    expect(Comment::query()->findOrFail($response->json('id'))->parent_id)->toBe($root->id);
});

it('rejects a parent comment belonging to another event', function () {
    $author = d24User();
    $event = d24Event(d24User());
    $otherEvent = d24Event(d24User(), 'Different event');
    $otherComment = d24Comment($otherEvent, d24User());

    $this->actingAs($author)
        ->postJson(route('events.comments.store', $event), [
            'body' => 'A reply must remain within this event thread.',
            'parent_id' => $otherComment->id,
        ])
        ->assertUnprocessable();
});

it('rejects a reply to a reply to keep the thread one level deep', function () {
    $author = d24User();
    $event = d24Event(d24User());
    $root = d24Comment($event, d24User());
    $reply = d24Comment($event, d24User(), ['parent_id' => $root->id]);

    $this->actingAs($author)
        ->postJson(route('events.comments.store', $event), [
            'body' => 'A nested reply is not allowed by the comment contract.',
            'parent_id' => $reply->id,
        ])
        ->assertUnprocessable();
});

it('shows pending comments only to their author and moderators', function () {
    $author = d24User();
    $viewer = d24User();
    $event = d24Event(d24User());
    $pending = d24Comment($event, $author, [
        'body' => 'A pending comment visible only to its author.',
        'status' => Comment::STATUS_PENDING,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertDontSee($pending->body);

    $this->actingAs($author)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee($pending->body)
        ->assertSee('на модерации');
});

it('denies comment creation for users without permission and for banned users', function (bool $banned) {
    $author = $banned
        ? d24User()
        : User::factory()->create(['email_verified_at' => now()]);
    if ($banned) {
        $author->forceFill(['banned_until' => now()->addDay()])->save();
    }
    $event = d24Event(d24User());

    $this->actingAs($author)
        ->postJson(route('events.comments.store', $event), [
            'body' => 'A comment that should not be accepted.',
        ])
        ->assertForbidden();
})->with([
    'no permission' => [false],
    'banned' => [true],
]);

it('requires verified email before accepting an event comment', function () {
    $this->actingAs(d24User(verified: false))
        ->post(route('events.comments.store', d24Event(d24User())), [
            'body' => 'An unverified account cannot post comments.',
        ])
        ->assertRedirect(route('verification.notice'));
});
