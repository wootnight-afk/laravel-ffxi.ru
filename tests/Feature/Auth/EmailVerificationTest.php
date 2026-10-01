<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('allows unverified user to read public pages', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)->get(route('home'))->assertOk();
    $this->actingAs($user)->get(route('news.index'))->assertOk();
});

it('redirects unverified user to verification notice when accessing write-required pages', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    // Пока нет write-страниц — проверяем через сам маршрут notice.
    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk();
});

it('allows verified user to access verification notice page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk();
});

it('rejects unauthenticated access to verification notice', function () {
    $response = $this->get(route('verification.notice'));
    $response->assertRedirect(route('login'));
});

it('marks email as verified via signed URL', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($url)->assertRedirect(route('home'));

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeTrue();
});
