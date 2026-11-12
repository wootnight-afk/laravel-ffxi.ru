<?php

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;

function galleryViewer(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function playerAlbum(User $owner, array $overrides = []): Album
{
    return Album::create(array_merge([
        'user_id' => $owner->id,
        'scope' => Album::SCOPE_PLAYER,
        'title' => 'Player album '.uniqid(),
        'slug' => 'player-album-'.uniqid(),
        'is_published' => true,
    ], $overrides));
}

function playerPhoto(Album $album, User $owner, array $overrides = []): Photo
{
    return Photo::create(array_merge([
        'album_id' => $album->id,
        'user_id' => $owner->id,
        'path_original' => 'photos/'.uniqid().'_orig.webp',
        'path_medium' => 'photos/'.uniqid().'_medium.webp',
        'path_thumb' => 'photos/'.uniqid().'_thumb.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ], $overrides));
}

it('lets the owner see the own gallery even when it is hidden', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => false])->save();

    $album = playerAlbum($owner);
    $photo = playerPhoto($album, $owner);

    $this->actingAs($owner)
        ->get(route('gallery.player.album', [$owner->name, $album->slug]))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('gallery.player.photo', [$owner->name, $album->slug, $photo->id]))
        ->assertOk();
});

it('lets an admin see a hidden player gallery', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => false])->save();

    $album = playerAlbum($owner);

    $this->actingAs($admin)
        ->get(route('gallery.player.album', [$owner->name, $album->slug]))
        ->assertOk();
});

it('hides the gallery from strangers when the profile is private', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => false, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner);
    $stranger = galleryViewer();

    $this->actingAs($stranger)
        ->get(route('gallery.player.album', [$owner->name, $album->slug]))
        ->assertForbidden();
});

it('hides the gallery from strangers when the gallery switch is off', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => false])->save();

    $album = playerAlbum($owner);
    $stranger = galleryViewer();

    $this->actingAs($stranger)
        ->get(route('gallery.player.album', [$owner->name, $album->slug]))
        ->assertForbidden();
});

it('shows published photos of a public player gallery', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner);
    $photo = playerPhoto($album, $owner);
    $stranger = galleryViewer();

    $this->actingAs($stranger)
        ->get(route('gallery.player.photo', [$owner->name, $album->slug, $photo->id]))
        ->assertOk();
});

it('hides unpublished photos from strangers', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner);
    $photo = playerPhoto($album, $owner, ['is_published' => false]);
    $stranger = galleryViewer();

    $this->actingAs($stranger)
        ->get(route('gallery.player.photo', [$owner->name, $album->slug, $photo->id]))
        ->assertNotFound();
});

it('rejects a photo that belongs to another album', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner);
    $otherAlbum = playerAlbum($owner);
    $photo = playerPhoto($otherAlbum, $owner);

    $this->actingAs($owner)
        ->get(route('gallery.player.photo', [$owner->name, $album->slug, $photo->id]))
        ->assertNotFound();
});

it('rejects an album that belongs to another user', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $otherOwner = galleryViewer();
    $otherOwner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($otherOwner);
    $stranger = galleryViewer();

    $this->actingAs($stranger)
        ->get(route('gallery.player.album', [$owner->name, $album->slug]))
        ->assertNotFound();
});

it('does not show the photo block on the profile when the gallery is off', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => false])->save();

    $album = playerAlbum($owner);
    playerPhoto($album, $owner);

    $stranger = galleryViewer();

    $this->actingAs($stranger)
        ->get(route('players.show', $owner->name))
        ->assertOk()
        ->assertDontSee(route('gallery.player.index', $owner->name), false);
});

it('deep-links a gallery overview thumbnail to the clicked photo', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner);
    $photo = playerPhoto($album, $owner);

    $viewer = galleryViewer();

    $this->actingAs($viewer)
        ->get(route('gallery.player.index', $owner->name))
        ->assertOk()
        ->assertSee(route('gallery.player.album', [
            'user' => $owner->name,
            'album' => $album->slug,
            'photo' => $photo->id,
        ]), false);
});

it('exposes the photo id on player album slides and keeps a plain album visit working', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner);
    $photo = playerPhoto($album, $owner);

    $viewer = galleryViewer();

    $this->actingAs($viewer)
        ->get(route('gallery.player.album', [$owner->name, $album->slug]))
        ->assertOk()
        ->assertSee('data-photo-id="'.$photo->id.'"', false)
        ->assertSee('data-details-label=', false);

    // The deep link parameter is consumed by the client; the server response
    // stays a normal album page and must not break.
    $this->actingAs($viewer)
        ->get(route('gallery.player.album', [
            'user' => $owner->name,
            'album' => $album->slug,
            'photo' => 999999,
        ]))
        ->assertOk()
        ->assertSee('class="gallery-grid"', false);
});

it('localizes the player gallery overview', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner, [
        'title' => 'Русский альбом игрока',
        'title_en' => 'English player album',
    ]);
    playerPhoto($album, $owner);

    $viewer = User::factory()->create(['email_verified_at' => now(), 'locale' => 'en']);
    $viewer->assignRole('user');

    $this->actingAs($viewer)
        ->get(route('gallery.player.index', $owner->name))
        ->assertOk()
        ->assertSee('English player album')
        ->assertDontSee('Русский альбом игрока');
});

it('shows only personal gallery photos on the profile', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner);
    $photo = playerPhoto($album, $owner);

    // A site album photo uploaded by the same user must not leak into the
    // profile block: gallery.player.photo never resolves site albums.
    $siteAlbum = Album::create([
        'user_id' => $owner->id,
        'scope' => Album::SCOPE_SITE,
        'title' => 'Site album '.uniqid(),
        'slug' => 'site-album-'.uniqid(),
        'is_published' => true,
    ]);

    Photo::create([
        'album_id' => $siteAlbum->id,
        'user_id' => $owner->id,
        'path_original' => 'photos/'.uniqid().'_orig.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $viewer = galleryViewer();

    $this->actingAs($viewer)
        ->get(route('players.show', $owner->name))
        ->assertOk()
        ->assertSee(route('gallery.player.photo', [$owner->name, $album->slug, $photo->id]), false)
        ->assertDontSee($siteAlbum->slug, false);
});

it('hides photos of unpublished albums from strangers on the profile', function () {
    $owner = galleryViewer();
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = playerAlbum($owner, ['is_published' => false]);
    $photo = playerPhoto($album, $owner);

    $link = route('gallery.player.photo', [$owner->name, $album->slug, $photo->id]);
    $viewer = galleryViewer();

    $this->actingAs($viewer)
        ->get(route('players.show', $owner->name))
        ->assertOk()
        ->assertDontSee($link, false);

    // The owner still sees the personal gallery preview on the profile.
    $this->actingAs($owner)
        ->get(route('players.show', $owner->name))
        ->assertOk()
        ->assertSee($link, false);
});
