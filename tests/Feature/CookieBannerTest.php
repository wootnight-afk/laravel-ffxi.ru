<?php

it('renders the cookie banner with the dismiss hooks for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-cookie-banner', false)
        ->assertSee('data-cookie-banner-dismiss', false)
        ->assertSee('Понятно', false);
});

it('hides the cookie banner once acknowledged', function () {
    $this->withCookie('cookie_banner_ack', '1')
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-cookie-banner', false);
});

it('keeps the javascript dismiss hooks in sync with the markup', function () {
    $js = file_get_contents(resource_path('js/app.js'));

    expect($js)
        ->toContain('[data-cookie-banner]')
        ->toContain('[data-cookie-banner-dismiss]')
        ->toContain('cookie_banner_ack');
});
