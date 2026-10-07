<?php

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use App\Services\SettingsRepository;

/**
 * Alpine.js ships with Livewire and is only injected when a Livewire component
 * renders. Guest pages have no Livewire component, so the three widgets below
 * were moved to vanilla JS + data-attributes. These tests guard the markup
 * hooks that the JS in resources/js/app.js binds to.
 */
it('renders the theme toggle without Alpine directives', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme-toggle', false)
        ->assertSee('data-theme-icon="light"', false)
        ->assertSee('data-theme-icon="dark"', false)
        ->assertSee('ffxi-theme', false)
        ->assertDontSee('x-data="themeToggle()"', false)
        ->assertDontSee('x-on:click="toggle()"', false);
});

it('renders the photo zoom without Alpine directives', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $album = Album::create([
        'user_id' => $admin->id,
        'scope' => Album::SCOPE_SITE,
        'title' => 'Zoom Album',
        'slug' => 'zoom-album-'.uniqid(),
        'is_published' => true,
    ]);

    $photo = Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/test_orig.webp',
        'path_medium' => 'photos/test_medium.webp',
        'path_thumb' => 'photos/test_thumb.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $this->get(route('gallery.photo', [$album->slug, $photo->id]))
        ->assertOk()
        ->assertSee('data-photo-zoom-open', false)
        ->assertSee('data-photo-zoom-overlay', false)
        ->assertSee('data-photo-zoom ', false)
        ->assertDontSee('x-data="{ zoomed: false }"', false)
        ->assertDontSee('<template x-if', false);
});

it('renders the nickname check without Alpine directives', function () {
    app(SettingsRepository::class)->set('registration_open', true);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('data-nickname-check-input', false)
        ->assertSee('data-nickname-check-url', false)
        ->assertSee('data-nickname-status="checking"', false)
        ->assertSee('data-nickname-status="available"', false)
        ->assertSee('data-nickname-status="taken"', false)
        ->assertSee('data-nickname-status="invalid"', false)
        ->assertSee('data-nickname-suggestions', false)
        ->assertSee('data-register-submit', false)
        ->assertDontSee('x-data="registrationForm()"', false)
        ->assertDontSee('x-show="status', false);
});

it('keeps the vanilla js hooks in sync with the markup', function () {
    $js = file_get_contents(resource_path('js/app.js'));

    expect($js)
        ->toContain('[data-theme-toggle]')
        ->toContain('ffxi-theme')
        ->toContain('[data-photo-zoom]')
        ->toContain('[data-photo-zoom-open]')
        ->toContain('[data-photo-zoom-overlay]')
        ->toContain('[data-nickname-check-input]')
        ->toContain('[data-nickname-status]')
        ->toContain('[data-nickname-suggestions]')
        ->toContain('[data-register-submit]');
});
