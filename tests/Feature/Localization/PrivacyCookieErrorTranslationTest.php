<?php

declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

it('renders the privacy page in both locales', function () {
    $this->withSession(['locale' => 'ru'])
        ->get(route('privacy'))
        ->assertOk()
        ->assertSee('Политика обработки персональных данных')
        ->assertSee('Раздел в разработке.')
        ->assertDontSee('This section is under development.');

    $this->withSession(['locale' => 'en'])
        ->get(route('privacy'))
        ->assertOk()
        ->assertSee('Personal Data Processing Policy')
        ->assertSee('This section is under development.')
        ->assertDontSee('Раздел в разработке.');
});

it('renders the cookie page with every technical cookie in both locales', function () {
    $this->withSession(['locale' => 'ru'])
        ->get(route('cookie'))
        ->assertOk()
        ->assertSee('Использование cookie')
        ->assertSee('ffxi-session')
        ->assertSee('XSRF-TOKEN')
        ->assertSee('guest_uid')
        ->assertSee('locale')
        ->assertSee('tz')
        ->assertSee('cookie_banner_ack')
        ->assertSee('Локальное хранилище')
        ->assertDontSee('Cookie usage');

    $this->withSession(['locale' => 'en'])
        ->get(route('cookie'))
        ->assertOk()
        ->assertSee('Cookie usage')
        ->assertSee('Selected interface language')
        ->assertSee('Browser timezone used to display time')
        ->assertSee('Local storage')
        ->assertDontSee('Использование cookie');
});

it('renders every error page in russian', function () {
    $this->app->setLocale('ru');

    foreach (['403', '404', '419', '422', '429', '500'] as $code) {
        $html = view('errors.'.$code, [
            'exception' => new TooManyRequestsHttpException(30),
        ])->render();

        expect($html)
            ->toContain(__('site.errors.'.$code.'.title'))
            ->toContain(__('site.errors.'.$code.'.message'))
            ->toContain('На главную');
    }
});

it('renders every error page in english', function () {
    $this->app->setLocale('en');

    foreach (['403', '404', '419', '422', '429', '500'] as $code) {
        $html = view('errors.'.$code, [
            'exception' => new TooManyRequestsHttpException(30),
        ])->render();

        expect($html)
            ->toContain(__('site.errors.'.$code.'.title'))
            ->toContain(__('site.errors.'.$code.'.message'))
            ->toContain('Go to home page')
            ->not->toContain('На главную');
    }
});

it('localizes the 404 page of an actual request', function () {
    $this->withSession(['locale' => 'en'])
        ->get('/definitely-missing-page')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertDontSee('Страница не найдена');
});

it('shows the retry countdown on the 429 page', function () {
    $this->app->setLocale('en');

    $html = view('errors.429', [
        'exception' => new TooManyRequestsHttpException(45),
    ])->render();

    expect($html)
        ->toContain('data-retry-seconds="45"')
        ->toContain('Try again in 45 sec.');
});
