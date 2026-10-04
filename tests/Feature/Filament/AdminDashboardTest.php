<?php

declare(strict_types=1);

use App\Filament\Widgets\AdminDashboardStats;
use App\Filament\Widgets\RecentAuditLogs;
use App\Filament\Widgets\RegistrationsChart;
use App\Models\AdminAuditLog;
use App\Models\Album;
use App\Models\Comment;
use App\Models\GuestVisitor;
use App\Models\News;
use App\Models\Photo;
use App\Models\User;
use Livewire\Livewire;

function makeDashboardRoleUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('shows real admin statistics, chart and recent audit entries', function () {
    $admin = makeDashboardRoleUser('admin');
    $registeredUser = User::factory()->create(['created_at' => now()->subDay()]);
    $news = News::query()->create([
        'user_id' => $admin->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Dashboard news',
        'body' => 'Dashboard content.',
        'status' => News::STATUS_PUBLISHED,
    ]);
    $album = Album::query()->create([
        'scope' => Album::SCOPE_SITE,
        'title' => 'Dashboard album',
        'slug' => 'dashboard-album',
    ]);
    Photo::query()->create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/dashboard.jpg',
    ]);
    Comment::query()->create([
        'user_id' => $registeredUser->id,
        'commentable_type' => News::class,
        'commentable_id' => $news->id,
        'body' => 'Pending dashboard comment.',
        'status' => Comment::STATUS_PENDING,
    ]);
    GuestVisitor::query()->create([
        'uuid' => (string) str()->uuid(),
        'display_name' => 'guest999',
        'ip_hash' => str_repeat('a', 64),
        'first_seen_at' => now()->subMinutes(3),
        'last_seen_at' => now()->subMinutes(2),
        'converted_user_id' => null,
    ]);
    AdminAuditLog::create([
        'user_id' => $admin->id,
        'action' => 'dashboard.test',
        'subject_type' => $news::class,
        'subject_id' => $news->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('App\\Filament\\Widgets\\AdminDashboardStats')
        ->assertSee('App\\Filament\\Widgets\\RegistrationsChart')
        ->assertSee('App\\Filament\\Widgets\\RecentAuditLogs');

    Livewire::actingAs($admin)->test(AdminDashboardStats::class)
        ->assertSee('Пользователи')
        ->assertSee('Новости')
        ->assertSee('Комментарии на модерации')
        ->assertSee('Фотографии')
        ->assertSee('Гости онлайн');

    Livewire::actingAs($admin)->test(RegistrationsChart::class)
        ->assertSee('Регистрации за 30 дней');

    Livewire::actingAs($admin)->test(RecentAuditLogs::class)
        ->assertSee('Последние административные действия')
        ->assertSee('dashboard.test');

    expect($registeredUser->exists)->toBeTrue();
});

it('shows editors content statistics but not system statistics or audit logs', function () {
    $editor = makeDashboardRoleUser('editor');
    News::query()->create([
        'user_id' => $editor->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Editor dashboard news',
        'body' => 'Editor content.',
        'status' => News::STATUS_PUBLISHED,
    ]);
    AdminAuditLog::create([
        'user_id' => $editor->id,
        'action' => 'hidden.audit.entry',
        'created_at' => now(),
    ]);

    $this->actingAs($editor)->get('/admin')
        ->assertOk()
        ->assertSee('App\\Filament\\Widgets\\AdminDashboardStats')
        ->assertDontSee('App\\Filament\\Widgets\\RegistrationsChart')
        ->assertDontSee('App\\Filament\\Widgets\\RecentAuditLogs');

    Livewire::actingAs($editor)->test(AdminDashboardStats::class)
        ->assertSee('Новости сайта')
        ->assertSee('Комментарии на модерации')
        ->assertDontSee('Пользователи');
});

it('redirects admin panel requests to Russian locale', function () {
    $admin = makeDashboardRoleUser('admin');

    $this->actingAs($admin)->get('/admin')->assertOk();

    expect(app()->getLocale())->toBe('ru');
});
