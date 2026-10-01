<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSocialLink;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

/**
 * Создаёт зарегистрированного пользователя с ролью `user`.
 * Роль обязательна: Policies проверяют разрешения (`profile.edit_own`),
 * а `User::factory()` по умолчанию их не назначает.
 */
function makeRegisteredUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('user');

    return $user;
}

beforeEach(function () {
    config()->set('social.max_links_per_user', 10);
});

// ------------------------------------------------------------------
// Валидация схем URL (spec §9.21)
// ------------------------------------------------------------------

it('accepts https url', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'https://example.com/profile',
    ]);

    $response->assertRedirect(route('cabinet.tab', ['tab' => 'social']));

    expect(UserSocialLink::where('user_id', $user->id)->count())->toBe(1);

    $link = UserSocialLink::where('user_id', $user->id)->first();
    expect($link->url)->toBe('https://example.com/profile');
    expect($link->is_visible)->toBeFalse();
});

it('rejects http url', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'http://example.com/profile',
    ]);

    $response->assertSessionHasErrors('url');
    expect(UserSocialLink::where('user_id', $user->id)->count())->toBe(0);
});

it('rejects javascript scheme', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'javascript:alert(1)',
    ]);

    $response->assertSessionHasErrors('url');
    expect(UserSocialLink::where('user_id', $user->id)->count())->toBe(0);
});

it('rejects data scheme', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'data:text/html,<script>alert(1)</script>',
    ]);

    $response->assertSessionHasErrors('url');
});

it('rejects file scheme', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'file:///etc/passwd',
    ]);

    $response->assertSessionHasErrors('url');
});

it('rejects mixed case javascript scheme', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'JaVaScRiPt:alert(1)',
    ]);

    $response->assertSessionHasErrors('url');
});

it('rejects unknown platform', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'not-a-real-platform',
        'url' => 'https://example.com/profile',
    ]);

    $response->assertSessionHasErrors('type');
});

// ------------------------------------------------------------------
// Лимит ссылок (spec §6.14)
// ------------------------------------------------------------------

it('enforces max links limit', function () {
    config()->set('social.max_links_per_user', 3);

    $user = makeRegisteredUser();
    actingAs($user);

    for ($i = 0; $i < 3; $i++) {
        UserSocialLink::create([
            'user_id' => $user->id,
            'type' => 'other',
            'url' => "https://example.com/{$i}",
            'is_visible' => false,
            'sort_order' => $i,
        ]);
    }

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'https://example.com/overflow',
    ]);

    $response->assertSessionHasErrors('url');
    expect(UserSocialLink::where('user_id', $user->id)->count())->toBe(3);
});

// ------------------------------------------------------------------
// Шаблон username → URL (spec §6.14)
// ------------------------------------------------------------------

it('builds url from username template for telegram', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'telegram',
        'username' => 'myhandle',
    ]);

    $response->assertRedirect(route('cabinet.tab', ['tab' => 'social']));

    $link = UserSocialLink::where('user_id', $user->id)->first();
    expect($link)->not->toBeNull();
    expect($link->url)->toBe('https://t.me/myhandle');
});

it('rejects telegram without username and url', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'telegram',
    ]);

    $response->assertSessionHasErrors('url');
    expect(UserSocialLink::where('user_id', $user->id)->count())->toBe(0);
});

// ------------------------------------------------------------------
// Ownership (spec §6.14 + Policy)
// ------------------------------------------------------------------

it('forbids updating another user link', function () {
    $owner = makeRegisteredUser();
    $attacker = makeRegisteredUser();

    $link = UserSocialLink::create([
        'user_id' => $owner->id,
        'type' => 'other',
        'url' => 'https://example.com/owner',
        'is_visible' => true,
        'sort_order' => 0,
    ]);

    actingAs($attacker);

    $response = patch(route('cabinet.social.update', $link), [
        'type' => 'other',
        'url' => 'https://evil.example.com',
    ]);

    $response->assertForbidden();

    expect($link->fresh()->url)->toBe('https://example.com/owner');
});

it('forbids deleting another user link', function () {
    $owner = makeRegisteredUser();
    $attacker = makeRegisteredUser();

    $link = UserSocialLink::create([
        'user_id' => $owner->id,
        'type' => 'other',
        'url' => 'https://example.com/owner',
        'is_visible' => true,
        'sort_order' => 0,
    ]);

    actingAs($attacker);

    $response = delete(route('cabinet.social.destroy', $link));

    $response->assertForbidden();

    expect(UserSocialLink::find($link->id))->not->toBeNull();
});

it('allows owner to toggle visibility', function () {
    $user = makeRegisteredUser();
    actingAs($user);

    $link = UserSocialLink::create([
        'user_id' => $user->id,
        'type' => 'other',
        'url' => 'https://example.com/profile',
        'is_visible' => false,
        'sort_order' => 0,
    ]);

    post(route('cabinet.social.toggle', $link))->assertRedirect();

    expect($link->fresh()->is_visible)->toBeTrue();
});

// ------------------------------------------------------------------
// Verified email + Policy (A.8: B1–B3)
// ------------------------------------------------------------------

it('redirects unverified user away from social link store', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'https://example.com/profile',
    ]);

    $response->assertRedirect(route('verification.notice'));
    expect(UserSocialLink::where('user_id', $user->id)->count())->toBe(0);
});

it('forbids store for verified user without profile.edit_own permission', function () {
    $user = User::factory()->create();
    // Роль `user` не назначаем — у голого пользователя нет разрешений.
    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'https://example.com/profile',
    ]);

    $response->assertForbidden();
    expect(UserSocialLink::where('user_id', $user->id)->count())->toBe(0);
});

it('forbids toggle for verified user without profile.edit_own permission', function () {
    $user = User::factory()->create();
    actingAs($user);

    $link = UserSocialLink::create([
        'user_id' => $user->id,
        'type' => 'other',
        'url' => 'https://example.com/profile',
        'is_visible' => false,
        'sort_order' => 0,
    ]);

    post(route('cabinet.social.toggle', $link))->assertForbidden();

    expect($link->fresh()->is_visible)->toBeFalse();
});
