<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Contract from frontend-spec.md §3.2 and §9.17:
 *   - guest without guest_sections[key] → 404 (not redirect to /login);
 *   - auth user without section.{key}.view permission → 403;
 *   - changes take effect immediately (settings cache flushed on save).
 */
function resetGuestSections(): void
{
    app(SettingsRepository::class)->set('guest_sections', [
        'home' => true,
        'news' => true,
        'gallery' => true,
        'contacts' => true,
        'events' => true,
        'players' => false,
        'player_profiles' => false,
    ]);
}

function disableGuestSection(string $key): void
{
    $repo = app(SettingsRepository::class);
    $current = $repo->get('guest_sections', []);
    $current[$key] = false;
    $repo->set('guest_sections', $current);
}

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    resetGuestSections();
});

// ------------------------------------------------------------------
// Guest — guest_sections JSON
// ------------------------------------------------------------------

it('returns 404 for guest when news section is disabled', function () {
    disableGuestSection('news');

    get(route('news.index'))->assertNotFound();
});

it('allows guest to read news when section is enabled', function () {
    // defaults leave news enabled
    get(route('news.index'))->assertOk();
});

it('returns 404 for guest when gallery section is disabled', function () {
    disableGuestSection('gallery');

    get(route('gallery.index'))->assertNotFound();
});

// ------------------------------------------------------------------
// Auth user — section.{key}.view permission
// ------------------------------------------------------------------

it('returns 403 for auth user without section.news.view permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    // no role → no section permissions

    actingAs($user)->get(route('news.index'))->assertForbidden();
});

it('allows auth user with section.news.view permission to read news', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    actingAs($user)->get(route('news.index'))->assertOk();
});
