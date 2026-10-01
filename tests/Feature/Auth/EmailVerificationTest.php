<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSocialLink;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

// ------------------------------------------------------------------
// Reading — public pages are available to unverified users
// ------------------------------------------------------------------

it('allows unverified user to read public pages', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    actingAs($user)->get(route('home'))->assertOk();
    actingAs($user)->get(route('news.index'))->assertOk();
});

// ------------------------------------------------------------------
// Writing — cabinet write routes require verified email (spec §3.1)
// ------------------------------------------------------------------

it('redirects unverified user to verification notice when accessing write-required pages', function () {
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

it('allows verified user to access cabinet write routes', function () {
    $user = User::factory()->create(); // email_verified_at => now() by factory
    $user->assignRole('user');

    actingAs($user);

    $response = post(route('cabinet.social.store'), [
        'type' => 'other',
        'url' => 'https://example.com/profile',
    ]);

    // Reaches the controller — redirect to cabinet tab, not to verification notice.
    $response->assertRedirect(route('cabinet.tab', ['tab' => 'social']));
    expect(UserSocialLink::where('user_id', $user->id)->count())->toBe(1);
});

// ------------------------------------------------------------------
// Notice page
// ------------------------------------------------------------------

it('allows verified user to access verification notice page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk();
});

it('rejects unauthenticated access to verification notice', function () {
    $response = $this->get(route('verification.notice'));
    $response->assertRedirect(route('login'));
});

// ------------------------------------------------------------------
// Signed-URL verification flow
// ------------------------------------------------------------------

it('marks email as verified via signed URL', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    actingAs($user)->get($url)->assertRedirect(route('home'));

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeTrue();
});
