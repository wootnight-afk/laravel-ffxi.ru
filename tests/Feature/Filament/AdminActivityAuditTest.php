<?php

declare(strict_types=1);

use App\Filament\Concerns\LogsAdminActivity;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\AdminAuditLog;
use App\Models\Comment;
use App\Models\News;
use App\Models\User;
use App\Models\UserRank;
use App\Services\AdminActivityLogger;
use Livewire\Livewire;

function makeAuditActor(string $role = 'admin'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeAuditComment(User $author, array $overrides = []): Comment
{
    $news = News::create([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Audit target '.uniqid(),
        'slug' => 'audit-target-'.uniqid(),
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
// Resource mutations
// ------------------------------------------------------------------

it('records an audit entry when a user is updated through the resource', function () {
    $admin = makeAuditActor();
    // Minute-aligned so the seconds-less form round trip does not register as
    // a change; the payload must then contain only the rank we actually set.
    $target = User::factory()->create(['email_verified_at' => now()->startOfMinute()]);
    $rank = UserRank::create(['key' => 'audit-rank', 'title' => 'Audit rank']);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.rank_id', $rank->getKey())
        ->call('save')
        ->assertHasNoErrors();

    $log = AdminAuditLog::query()->where('action', 'user.updated')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe(User::class)
        ->and($log->subject_id)->toBe($target->getKey())
        ->and($log->user_id)->toBe($admin->getKey())
        ->and($log->new)->toBe(['rank_id' => $rank->getKey()]);
});

it('records exactly one audit entry per created news item', function () {
    $admin = makeAuditActor();

    Livewire::actingAs($admin)
        ->test(CreateNews::class)
        ->set('data.title', 'Audited news')
        ->set('data.scope', News::SCOPE_SITE)
        ->set('data.status', News::STATUS_DRAFT)
        ->set('data.body', 'Body')
        ->set('data.user_id', $admin->getKey())
        ->call('create')
        ->assertHasNoErrors();

    expect(AdminAuditLog::query()->where('action', 'news.created')->count())->toBe(1);
});

it('records a deletion performed through a table delete action', function () {
    $editor = makeAuditActor('editor');
    $comment = makeAuditComment($editor);

    Livewire::actingAs($editor)
        ->test(ListComments::class)
        ->callTableAction('delete', $comment);

    $log = AdminAuditLog::query()->where('action', 'comment.deleted')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_id)->toBe($comment->getKey());
});

it('records explicit moderation actions', function () {
    $editor = makeAuditActor('editor');
    $comment = makeAuditComment($editor, ['status' => Comment::STATUS_PENDING]);

    Livewire::actingAs($editor)
        ->test(ListComments::class)
        ->callTableAction('approve', $comment)
        ->assertHasNoTableActionErrors();

    expect(AdminAuditLog::query()->where('action', 'comment.approved')->count())->toBe(1);
});

// ------------------------------------------------------------------
// Redaction
// ------------------------------------------------------------------

it('redacts PII and drops MFA secrets from the audit payload', function () {
    $subject = User::factory()->create();

    app(AdminActivityLogger::class)->record(
        subject: $subject,
        verb: 'updated',
        old: ['email' => 'old@example.com', 'phone' => '+70000000000', 'password' => 'old-hash'],
        new: [
            'email' => 'new@example.com',
            'phone' => '+71111111111',
            'password' => 'new-hash',
            'app_authentication_secret' => 'SUPERSECRET',
            'app_authentication_recovery_codes' => 'RECOVERYCODES',
        ],
    );

    $log = AdminAuditLog::query()->where('action', 'user.updated')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->old)->toBe([
            'email' => '[changed]',
            'phone' => '[changed]',
            'password' => '[changed]',
        ])
        ->and($log->new)->toBe([
            'email' => '[changed]',
            'phone' => '[changed]',
            'password' => '[changed]',
        ]);

    $payload = (string) json_encode([$log->old, $log->new]);

    expect($payload)
        ->not->toContain('new@example.com')
        ->not->toContain('old@example.com')
        ->not->toContain('+71111111111')
        ->not->toContain('new-hash')
        ->not->toContain('SUPERSECRET')
        ->not->toContain('RECOVERYCODES');
});

it('skips audit entries when an update changes nothing recordable', function () {
    $subject = User::factory()->create();

    $log = app(AdminActivityLogger::class)->record(
        subject: $subject,
        verb: 'updated',
        new: ['app_authentication_secret' => 'SUPERSECRET'],
    );

    expect($log)->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'user.updated')->count())->toBe(0);
});

// ------------------------------------------------------------------
// Scope
// ------------------------------------------------------------------

it('does not attach the audit trait to the read-only activity log resource', function () {
    expect(class_uses_recursive(ActivityLogResource::class))
        ->not->toContain(LogsAdminActivity::class);
});

it('keeps the audit trail free of plaintext PII for user updates', function () {
    $admin = makeAuditActor();
    $target = User::factory()->create(['email' => 'player@example.com']);
    $rank = UserRank::create(['key' => 'pii-rank', 'title' => 'PII rank']);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.rank_id', $rank->getKey())
        ->call('save');

    $payloads = AdminAuditLog::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $target->getKey())
        ->pluck('new')
        ->map(fn (?array $payload): string => (string) json_encode($payload))
        ->implode(' ');

    expect($payloads)->toContain('rank_id')
        ->and($payloads)->not->toContain('player@example.com')
        ->and($payloads)->not->toContain('"email"');
});
