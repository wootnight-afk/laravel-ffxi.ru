<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Filament\Resources\CommentResource;
use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Filament\Resources\DashboardWidgetResource;
use App\Filament\Resources\DashboardWidgetResource\Pages\ListDashboardWidgets;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\EventTypeResource;
use App\Filament\Resources\EventTypeResource\Pages\ListEventTypes;
use App\Filament\Resources\GalleryResource;
use App\Filament\Resources\GalleryResource\Pages\ListAlbums;
use App\Filament\Resources\GuestResource;
use App\Filament\Resources\GuestResource\Pages\ListGuests;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Filament\Resources\NewsResource\Pages\EditNews;
use App\Filament\Resources\NewsResource\Pages\ListNews;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Resources\RankResource;
use App\Filament\Resources\RankResource\Pages\ListRanks;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\AdminAuditLog;
use App\Models\News;
use App\Models\User;
use App\Models\UserRank;
use App\Services\SettingsRepository;
use Filament\Forms\Components\MarkdownEditor;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

function makeAcceptanceAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

function makeAcceptanceEditor(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('editor');

    return $user;
}

function makeAcceptanceUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

/**
 * Every admin route that must be guarded by server-side authorization.
 *
 * @return array<string, string>
 */
function adminRouteMatrix(int $userId, int $newsId): array
{
    return [
        'dashboard' => '/admin',
        'users index' => '/admin/users',
        'user edit' => "/admin/users/{$userId}/edit",
        'roles index' => '/admin/roles',
        'guests index' => '/admin/guests',
        'news index' => '/admin/news',
        'news create' => '/admin/news/create',
        'news edit' => "/admin/news/{$newsId}/edit",
        'comments index' => '/admin/comments',
        'galleries index' => '/admin/galleries',
        'galleries create' => '/admin/galleries/create',
        'pages index' => '/admin/pages',
        'pages create' => '/admin/pages/create',
        'events index' => '/admin/events',
        'events create' => '/admin/events/create',
        'event types index' => '/admin/event-types',
        'event types create' => '/admin/event-types/create',
        'ranks index' => '/admin/ranks',
        'ranks create' => '/admin/ranks/create',
        'widgets index' => '/admin/dashboard-widgets',
        'widgets create' => '/admin/dashboard-widgets/create',
        'activity logs index' => '/admin/activity-logs',
        'matrix' => '/admin/matrix',
        'settings' => '/admin/settings',
    ];
}

// ------------------------------------------------------------------
// 1. Policy matrix
// ------------------------------------------------------------------

it('redirects guests from every admin route to the panel login', function () {
    $target = User::factory()->create();
    $news = News::create([
        'user_id' => $target->getKey(),
        'scope' => News::SCOPE_SITE,
        'title' => 'Guest guard news',
        'slug' => 'guest-guard-'.uniqid(),
        'body' => 'Body',
        'status' => News::STATUS_DRAFT,
    ]);

    foreach (adminRouteMatrix($target->getKey(), $news->getKey()) as $name => $url) {
        $this->get($url)->assertRedirectContains('/admin/login', "route: {$name}");
    }
});

it('returns 403 for regular users on every admin route', function () {
    $user = makeAcceptanceUser();
    $target = User::factory()->create();
    $news = News::create([
        'user_id' => $target->getKey(),
        'scope' => News::SCOPE_SITE,
        'title' => 'Forbidden guard news',
        'slug' => 'forbidden-guard-'.uniqid(),
        'body' => 'Body',
        'status' => News::STATUS_DRAFT,
    ]);

    foreach (adminRouteMatrix($target->getKey(), $news->getKey()) as $name => $url) {
        $this->actingAs($user)->get($url)->assertForbidden("route: {$name}");
    }
});

it('lets editors reach content surfaces and blocks community and system surfaces', function () {
    $editor = makeAcceptanceEditor();

    $allowed = [
        '/admin',
        '/admin/news',
        '/admin/comments',
        '/admin/galleries',
        '/admin/pages',
        '/admin/events',
    ];

    $forbidden = [
        '/admin/users',
        '/admin/roles',
        '/admin/guests',
        '/admin/ranks',
        '/admin/event-types',
        '/admin/dashboard-widgets',
        '/admin/activity-logs',
        '/admin/matrix',
        '/admin/settings',
    ];

    foreach ($allowed as $url) {
        $this->actingAs($editor)->get($url)->assertOk("allowed route: {$url}");
    }

    foreach ($forbidden as $url) {
        $this->actingAs($editor)->get($url)->assertForbidden("forbidden route: {$url}");
    }
});

it('blocks suspended and deletion-requested accounts from the panel entirely', function () {
    $suspended = User::factory()->create([
        'email_verified_at' => now(),
        'status' => UserStatus::Suspended,
        'suspended_at' => now(),
    ]);
    $suspended->assignRole('admin');

    $deletionRequested = User::factory()->create([
        'email_verified_at' => now(),
        'status' => UserStatus::DeletionRequested,
        'deletion_requested_at' => now(),
    ]);
    $deletionRequested->assignRole('admin');

    $this->actingAs($suspended)->get('/admin/users')->assertForbidden();
    $this->actingAs($deletionRequested)->get('/admin/users')->assertForbidden();
});

it('enforces resource-level authorization independently of the ui', function () {
    $editor = makeAcceptanceEditor();
    $admin = makeAcceptanceAdmin();

    $adminOnlyResources = [
        UserResource::class,
        RoleResource::class,
        GuestResource::class,
        RankResource::class,
        EventTypeResource::class,
        DashboardWidgetResource::class,
        ActivityLogResource::class,
    ];

    foreach ($adminOnlyResources as $resource) {
        $this->actingAs($editor);
        expect($resource::canViewAny())->toBeFalse("editor must not view {$resource}");

        $this->actingAs($admin);
        expect($resource::canViewAny())->toBeTrue("admin must view {$resource}");
    }

    $contentResources = [
        NewsResource::class,
        CommentResource::class,
        GalleryResource::class,
        PageResource::class,
        EventResource::class,
    ];

    foreach ($contentResources as $resource) {
        $this->actingAs($editor);
        expect($resource::canViewAny())->toBeTrue("editor must view {$resource}");
    }

    // Editor is a content administrator (R2): it may create site news but
    // must not reach the admin-only surfaces checked above.
    $this->actingAs($editor);
    expect(NewsResource::canCreate())->toBeTrue('editor manages content');

    $this->actingAs($admin);
    expect(RoleResource::canCreate())->toBeFalse('the role catalog stays read-only')
        ->and(UserResource::canDelete(User::factory()->create()))->toBeFalse('users are never hard-deleted from the ui');
});

// ------------------------------------------------------------------
// 2. Markdown pipeline (R1)
// ------------------------------------------------------------------

it('uses the markdown editor for news and page bodies', function () {
    $admin = makeAcceptanceAdmin();

    Livewire::actingAs($admin)
        ->test(CreateNews::class)
        ->assertFormFieldExists('body', fn ($field): bool => $field instanceof MarkdownEditor);

    Livewire::actingAs($admin)
        ->test(CreatePage::class)
        ->assertFormFieldExists('body', fn ($field): bool => $field instanceof MarkdownEditor);
});

it('keeps the markdown round trip stable across an update', function () {
    $admin = makeAcceptanceAdmin();

    Livewire::actingAs($admin)
        ->test(CreateNews::class)
        ->set('data.title', 'Round trip news')
        ->set('data.scope', News::SCOPE_SITE)
        ->set('data.user_id', $admin->getKey())
        ->set('data.status', News::STATUS_DRAFT)
        ->set('data.body', '**First** version')
        ->call('create')
        ->assertHasNoErrors();

    $news = News::query()->latest('id')->firstOrFail();

    expect($news->body)->toBe('**First** version')
        ->and($news->body_html)->toContain('<strong>First</strong>');

    Livewire::actingAs($admin)
        ->test(EditNews::class, ['record' => $news->getKey()])
        ->set('data.body', "**Second** version\n\n<script>alert(1)</script>")
        ->call('save')
        ->assertHasNoErrors();

    $news->refresh();

    expect($news->body)->toBe("**Second** version\n\n<script>alert(1)</script>")
        ->and($news->body_html)->toContain('<strong>Second</strong>')
        ->and($news->body_html)->not->toContain('<script')
        ->and($news->body_html)->not->toContain('<strong>First</strong>');
});

// ------------------------------------------------------------------
// 3. Audit (R4)
// ------------------------------------------------------------------

it('never stores plaintext pii or ip addresses in audit payloads', function () {
    $admin = makeAcceptanceAdmin();
    $target = User::factory()->create([
        'email' => 'audited-player@example.com',
        'phone' => '+79990001122',
    ]);
    $rank = UserRank::create(['key' => 'acceptance-rank', 'title' => 'Acceptance rank']);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.rank_id', $rank->getKey())
        ->call('save')
        ->assertHasNoErrors();

    $payloads = AdminAuditLog::query()
        ->get()
        ->flatMap(fn (AdminAuditLog $log): array => [$log->old, $log->new])
        ->filter()
        ->map(fn (array $payload): string => (string) json_encode($payload))
        ->implode(' ');

    expect($payloads)->toContain('rank_id')
        ->and($payloads)->not->toContain('audited-player@example.com')
        ->and($payloads)->not->toContain('+79990001122')
        ->and($payloads)->not->toContain('127.0.0.1');
});

it('writes exactly one audit entry per admin mutation', function () {
    $admin = makeAcceptanceAdmin();

    Livewire::actingAs($admin)
        ->test(CreateNews::class)
        ->set('data.title', 'Single entry news')
        ->set('data.scope', News::SCOPE_SITE)
        ->set('data.user_id', $admin->getKey())
        ->set('data.status', News::STATUS_DRAFT)
        ->set('data.body', 'Body')
        ->call('create')
        ->assertHasNoErrors();

    $news = News::query()->latest('id')->firstOrFail();

    expect(AdminAuditLog::query()->where('action', 'news.created')->count())->toBe(1);

    Livewire::actingAs($admin)
        ->test(EditNews::class, ['record' => $news->getKey()])
        ->set('data.title', 'Single entry news updated')
        ->call('save')
        ->assertHasNoErrors();

    expect(AdminAuditLog::query()->where('action', 'news.updated')->count())->toBe(1)
        ->and(AdminAuditLog::query()->where('action', 'news.created')->count())->toBe(1);
});

it('produces no audit noise from read-only list views', function () {
    $admin = makeAcceptanceAdmin();

    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/news')->assertOk();
    $this->actingAs($admin)->get('/admin/activity-logs')->assertOk();

    expect(AdminAuditLog::query()->count())->toBe(0);
});

// ------------------------------------------------------------------
// 4. Export excluded (R7)
// ------------------------------------------------------------------

it('does not expose any export action or export code in the panel', function () {
    $admin = makeAcceptanceAdmin();

    $listPages = [
        ListUsers::class,
        ListRoles::class,
        ListGuests::class,
        ListNews::class,
        ListComments::class,
        ListAlbums::class,
        ListPages::class,
        ListEvents::class,
        ListEventTypes::class,
        ListRanks::class,
        ListDashboardWidgets::class,
        ListActivityLogs::class,
    ];

    foreach ($listPages as $page) {
        Livewire::actingAs($admin)
            ->test($page)
            ->assertTableActionDoesNotExist('export');
    }

    $offenders = collect(File::allFiles(app_path('Filament')))
        ->filter(fn (SplFileInfo $file): bool => str_contains(File::get($file->getPathname()), 'Export'))
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->all();

    expect($offenders)->toBe([]);
});

// ------------------------------------------------------------------
// 5. Navigation (§17)
// ------------------------------------------------------------------

it('limits the sidebar to the four agreed navigation groups', function () {
    $classes = [
        ...File::files(app_path('Filament/Resources')),
        ...File::files(app_path('Filament/Pages')),
    ];

    $groups = collect($classes)
        ->map(function (SplFileInfo $file): ?string {
            $class = 'App\\Filament\\'.(str_contains($file->getPathname(), '/Resources/') ? 'Resources\\' : 'Pages\\')
                .$file->getBasename('.php');

            return class_exists($class) ? $class::getNavigationGroup() : null;
        })
        ->filter()
        ->unique()
        ->sort()
        ->values()
        ->all();

    $expected = collect(['main', 'community', 'content', 'system'])
        ->map(fn (string $key): string => __("filament.navigation.{$key}"))
        ->sort()
        ->values()
        ->all();

    expect($groups)->toBe($expected);
});

// ------------------------------------------------------------------
// 6. MFA (R5)
// ------------------------------------------------------------------

it('offers mfa self-service to editors without ever requiring it', function () {
    $editor = makeAcceptanceEditor();

    $this->actingAs($editor)->get('/admin/profile')->assertOk();

    app(SettingsRepository::class)->set('admin_2fa_required', true);

    $this->actingAs($editor)->get('/admin/news')->assertOk();
});

// ------------------------------------------------------------------
// 7. Regression: public site and stage 7 (R1, §15.7)
// ------------------------------------------------------------------

it('keeps the public site reachable for guests', function () {
    $this->get('/')->assertOk();
    $this->get('/news')->assertOk();
    $this->get('/gallery')->assertOk();
    $this->get('/contacts')->assertOk();
    $this->get('/events')->assertOk();
    $this->get('/privacy')->assertOk();
    $this->get('/cookie')->assertOk();
    $this->get('/login')->assertOk();
});

it('keeps the stage 7 activity feed and dashboard working', function () {
    $admin = makeAcceptanceAdmin();

    $this->actingAs($admin)->get('/activity')->assertOk();
    $this->actingAs($admin)->get('/admin')->assertOk();
});
