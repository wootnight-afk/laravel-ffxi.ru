<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;

it('uses russian as the fallback locale', function () {
    expect(config('app.fallback_locale'))->toBe('ru');
});

it('translates locale labels and interface messages', function () {
    app()->setLocale('ru');

    expect(Locale::Ru->label())->toBe('Русский')
        ->and(Locale::En->label())->toBe('English')
        ->and(__('site.locale.switch_failed'))->toBe('Не удалось переключить язык интерфейса. Попробуйте ещё раз.');

    app()->setLocale('en');

    expect(__('site.locale.switch_failed'))->toBe('Could not switch the interface language. Please try again.');
});

it('renders the locale switch error in the active interface language', function (string $locale, string $message) {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locale' => null,
    ]);

    User::saving(function (): void {
        throw new RuntimeException('database is unavailable');
    });

    $response = $this->actingAs($user)
        ->withSession(['locale' => $locale])
        ->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'en']);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors(['locale' => $message]);
})->with([
    'russian' => ['ru', 'Не удалось переключить язык интерфейса. Попробуйте ещё раз.'],
    'english' => ['en', 'Could not switch the interface language. Please try again.'],
]);
