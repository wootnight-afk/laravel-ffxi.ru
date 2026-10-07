<?php

it('renders the cookie banner with the dismiss hooks for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-cookie-banner', false)
        ->assertSee('data-cookie-banner-dismiss', false)
        ->assertSee('Понятно', false);
});

it('hides the cookie banner when the plain javascript cookie is present', function () {
    // JavaScript sets a plain (unencrypted) cookie, exactly as a browser sends
    // it. withUnencryptedCookie() reproduces that; withCookie() would encrypt
    // the value first and mask the encryption-related bug.
    $this->withUnencryptedCookie('cookie_banner_ack', '1')
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-cookie-banner', false);
});

it('keeps the javascript dismiss hooks in sync with the markup', function () {
    $js = file_get_contents(resource_path('js/app.js'));

    expect($js)
        ->toContain('[data-cookie-banner]')
        ->toContain('[data-cookie-banner-dismiss]')
        ->toContain("document.cookie = 'cookie_banner_ack=1; path=/; max-age=31536000; SameSite=Lax'");
});
