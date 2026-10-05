<?php

declare(strict_types=1);

use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Models\Comment;
use App\Models\News;
use App\Models\User;
use Livewire\Livewire;

function makeCommentPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeCommentRecord(User $author, array $overrides = []): Comment
{
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Comment target '.uniqid(),
        'slug' => 'comment-target-'.uniqid(),
        'body' => 'Target body',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    return Comment::create(array_merge([
        'user_id' => $author->id,
        'commentable_type' => News::class,
        'commentable_id' => $news->id,
        'body' => 'A comment body long enough to be valid.',
        'status' => Comment::STATUS_PENDING,
    ], $overrides));
}

// ------------------------------------------------------------------
// Access
// ------------------------------------------------------------------

it('allows editors to access the comment moderation resource', function () {
    $editor = makeCommentPanelUser('editor');

    $this->actingAs($editor)->get('/admin/comments')->assertOk();
});

it('denies regular users access to the comment resource', function () {
    $user = makeCommentPanelUser('user');

    $this->actingAs($user)->get('/admin/comments')->assertForbidden();
});

// ------------------------------------------------------------------
// Moderation transitions
// ------------------------------------------------------------------

it('approves a pending comment and clears the report flag', function () {
    $editor = makeCommentPanelUser('editor');
    $comment = makeCommentRecord($editor, ['status' => Comment::STATUS_PENDING, 'is_reported' => true]);

    Livewire::actingAs($editor)
        ->test(ListComments::class)
        ->callTableAction('approve', $comment)
        ->assertHasNoTableActionErrors();

    $comment->refresh();

    expect($comment->status)->toBe(Comment::STATUS_APPROVED)
        ->and($comment->is_reported)->toBeFalse();
});

it('rejects a comment through the table action', function () {
    $editor = makeCommentPanelUser('editor');
    $comment = makeCommentRecord($editor, ['status' => Comment::STATUS_PENDING]);

    Livewire::actingAs($editor)
        ->test(ListComments::class)
        ->callTableAction('reject', $comment)
        ->assertHasNoTableActionErrors();

    expect($comment->refresh()->status)->toBe(Comment::STATUS_REJECTED);
});

it('marks a comment as spam through the table action', function () {
    $editor = makeCommentPanelUser('editor');
    $comment = makeCommentRecord($editor, ['status' => Comment::STATUS_PENDING]);

    Livewire::actingAs($editor)
        ->test(ListComments::class)
        ->callTableAction('spam', $comment)
        ->assertHasNoTableActionErrors();

    expect($comment->refresh()->status)->toBe(Comment::STATUS_SPAM);
});

it('approves selected comments in bulk', function () {
    $editor = makeCommentPanelUser('editor');
    $first = makeCommentRecord($editor, ['status' => Comment::STATUS_PENDING]);
    $second = makeCommentRecord($editor, ['status' => Comment::STATUS_PENDING]);

    Livewire::actingAs($editor)
        ->test(ListComments::class)
        ->callTableBulkAction('approve', [$first, $second])
        ->assertHasNoTableBulkActionErrors();

    expect($first->refresh()->status)->toBe(Comment::STATUS_APPROVED)
        ->and($second->refresh()->status)->toBe(Comment::STATUS_APPROVED);
});

// ------------------------------------------------------------------
// Plain-text contract
// ------------------------------------------------------------------

it('keeps the comment body as plain text and escapes it when rendered', function () {
    $editor = makeCommentPanelUser('editor');
    $payload = '<script>alert(1)</script> plain';
    $comment = makeCommentRecord($editor, ['body' => $payload, 'status' => Comment::STATUS_PENDING]);

    expect($comment->body)->toBe($payload);

    $this->actingAs($editor)
        ->get('/admin/comments')
        ->assertOk()
        ->assertSee('&lt;script&gt;', false);
});

// ------------------------------------------------------------------
// Filters
// ------------------------------------------------------------------

it('filters the comment list by status', function () {
    $editor = makeCommentPanelUser('editor');
    $spam = makeCommentRecord($editor, ['status' => Comment::STATUS_SPAM]);
    $approved = makeCommentRecord($editor, ['status' => Comment::STATUS_APPROVED]);

    Livewire::actingAs($editor)
        ->test(ListComments::class)
        ->filterTable('status', Comment::STATUS_SPAM)
        ->assertCanSeeTableRecords([$spam])
        ->assertCanNotSeeTableRecords([$approved]);
});
