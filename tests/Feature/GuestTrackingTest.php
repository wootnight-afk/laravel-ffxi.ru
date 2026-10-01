<?php

use App\Http\Middleware\IdentifyGuest;
use App\Models\GuestVisitor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    GuestVisitor::query()->delete();
    DB::statement('ALTER TABLE guest_visitors AUTO_INCREMENT = 1');
});

it('creates a guest visitor and sets the cookie', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    expect(GuestVisitor::count())->toBe(1);

    $visitor = GuestVisitor::first();
    expect($visitor->display_name)->toBe('guest001');
    expect($visitor->ip_hash)->toHaveLength(64);
    expect($visitor->hits)->toBe(1);

    $cookie = $response->getCookie(IdentifyGuest::COOKIE);
    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->toBe($visitor->uuid);
});

it('reuses the same visitor on subsequent requests', function () {
    $first = $this->get(route('home'));
    $cookie = $first->getCookie(IdentifyGuest::COOKIE);

    $this->withCookie(IdentifyGuest::COOKIE, $cookie->getValue())
        ->get(route('home'));
    $this->withCookie(IdentifyGuest::COOKIE, $cookie->getValue())
        ->get(route('news.index'));

    expect(GuestVisitor::count())->toBe(1);

    $visitor = GuestVisitor::first();
    expect($visitor->display_name)->toBe('guest001');
    expect($visitor->uuid)->toBe($cookie->getValue());
});

it('does not track authenticated users as guests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'));

    expect(GuestVisitor::count())->toBe(0);
});

it('assigns display names in guestNNN format', function () {
    $this->get(route('home'));

    $visitor = GuestVisitor::first();
    expect($visitor->display_name)->toMatch('/^guest\d{3,}$/');
    expect($visitor->display_name)->toStartWith('guest');
});
