<?php

declare(strict_types=1);

use App\Jobs\ProcessPhotoJob;
use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function makeGalleryUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function makePlayerAlbum(User $user, array $overrides = []): Album
{
    return Album::create(array_merge([
        'user_id' => $user->id,
        'scope' => Album::SCOPE_PLAYER,
        'title' => 'Album #'.uniqid(),
        'is_published' => true,
    ], $overrides));
}

function makeGalleryPhoto(Album $album, User $user, array $overrides = []): Photo
{
    return Photo::create(array_merge([
        'album_id' => $album->id,
        'user_id' => $user->id,
        'path_original' => 'photos/x_orig.webp',
        'path_medium' => 'photos/x_medium.webp',
        'path_thumb' => 'photos/x_thumb.webp',
        'width' => 100,
        'height' => 100,
        'size_bytes' => 100,
        'sort_order' => 0,
        'is_published' => true,
    ], $overrides));
}

function makePhotoJpeg(int $width = 400, int $height = 400): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));

    $path = tempnam(sys_get_temp_dir(), 'photo_').'.jpg';
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return $path;
}

/**
 * @return array<int, UploadedFile>
 */
function makeBatch(int $count): array
{
    $files = [];
    for ($i = 0; $i < $count; $i++) {
        $files[] = new UploadedFile(makePhotoJpeg(), "photo_{$i}.jpg", 'image/jpeg', null, true);
    }

    return $files;
}

beforeEach(function () {
    Cache::flush();
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();

    $settings = app(SettingsRepository::class);
    $settings->set('gallery_max_albums_per_user', 10);
    $settings->set('gallery_max_photos_per_day', 50);
});

// ------------------------------------------------------------------
// Access barriers
// ------------------------------------------------------------------

it('redirects guests from my-gallery tab', function () {
    get(route('cabinet.tab', ['tab' => 'gallery']))->assertRedirect(route('login'));
});

it('blocks unverified user from creating album', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('user');

    actingAs($user)
        ->post(route('cabinet.gallery.albums.store'), ['title' => 'My album'])
        ->assertRedirect(route('verification.notice'));
});

// ------------------------------------------------------------------
// Album — store
// ------------------------------------------------------------------

it('creates a player album published by default', function () {
    $user = makeGalleryUser();

    actingAs($user)
        ->post(route('cabinet.gallery.albums.store'), ['title' => 'Летние фото'])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'gallery']));

    $album = Album::where('user_id', $user->id)->first();
    expect($album)->not->toBeNull();
    expect($album->scope)->toBe(Album::SCOPE_PLAYER);
    expect($album->is_published)->toBeTrue();
});

it('rejects too short album title', function () {
    $user = makeGalleryUser();

    actingAs($user)
        ->post(route('cabinet.gallery.albums.store'), ['title' => 'x'])
        ->assertSessionHasErrors('title');
});

it('enforces album limit', function () {
    app(SettingsRepository::class)->set('gallery_max_albums_per_user', 2);
    $user = makeGalleryUser();
    makePlayerAlbum($user);
    makePlayerAlbum($user);

    actingAs($user)
        ->post(route('cabinet.gallery.albums.store'), ['title' => 'Третий'])
        ->assertSessionHasErrors('title');

    expect(Album::where('user_id', $user->id)->count())->toBe(2);
});

// ------------------------------------------------------------------
// Album — update
// ------------------------------------------------------------------

it('updates own album', function () {
    $user = makeGalleryUser();
    $album = makePlayerAlbum($user);

    actingAs($user)
        ->patch(route('cabinet.gallery.albums.update', $album), [
            'title' => 'Переименован',
            'is_published' => '0',
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'gallery']));

    $album->refresh();
    expect($album->title)->toBe('Переименован');
    expect($album->is_published)->toBeFalse();
});

it('forbids updating a foreign album', function () {
    $owner = makeGalleryUser();
    $attacker = makeGalleryUser();
    $album = makePlayerAlbum($owner);

    actingAs($attacker)
        ->patch(route('cabinet.gallery.albums.update', $album), ['title' => 'Взлом'])
        ->assertForbidden();

    expect($album->fresh()->title)->not->toBe('Взлом');
});

// ------------------------------------------------------------------
// Album — destroy
// ------------------------------------------------------------------

it('soft-deletes own album with its photos', function () {
    $user = makeGalleryUser();
    $album = makePlayerAlbum($user);
    makeGalleryPhoto($album, $user);

    actingAs($user)
        ->delete(route('cabinet.gallery.albums.destroy', $album))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'gallery']));

    expect(Album::find($album->id))->toBeNull();
    expect(Photo::where('album_id', $album->id)->count())->toBe(0);
});

it('forbids deleting a foreign album', function () {
    $owner = makeGalleryUser();
    $attacker = makeGalleryUser();
    $album = makePlayerAlbum($owner);

    actingAs($attacker)
        ->delete(route('cabinet.gallery.albums.destroy', $album))
        ->assertForbidden();

    expect(Album::find($album->id))->not->toBeNull();
});

// ------------------------------------------------------------------
// Photos — upload (queue)
// ------------------------------------------------------------------

it('dispatches ProcessPhotoJob for each uploaded file', function () {
    $user = makeGalleryUser();
    $album = makePlayerAlbum($user);

    actingAs($user)
        ->post(route('cabinet.gallery.photos.upload', $album), [
            'photos' => makeBatch(3),
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'gallery']));

    Queue::assertPushed(ProcessPhotoJob::class, 3);
});

it('rejects batch larger than ten', function () {
    $user = makeGalleryUser();
    $album = makePlayerAlbum($user);

    actingAs($user)
        ->post(route('cabinet.gallery.photos.upload', $album), [
            'photos' => makeBatch(11),
        ])
        ->assertSessionHasErrors('photos');

    Queue::assertNothingPushed();
});

it('enforces daily photo limit even for partial batch', function () {
    $user = makeGalleryUser();
    $album = makePlayerAlbum($user);

    // 47 photos already uploaded today.
    for ($i = 0; $i < 47; $i++) {
        makeGalleryPhoto($album, $user, [
            'path_original' => "photos/{$i}_orig.webp",
            'path_medium' => "photos/{$i}_medium.webp",
            'path_thumb' => "photos/{$i}_thumb.webp",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Attempt to upload 5 → 47 + 5 = 52 > 50 → reject.
    actingAs($user)
        ->post(route('cabinet.gallery.photos.upload', $album), [
            'photos' => makeBatch(5),
        ])
        ->assertSessionHasErrors('photos');

    Queue::assertNothingPushed();
});

it('allows upload when daily limit would be exactly met', function () {
    $user = makeGalleryUser();
    $album = makePlayerAlbum($user);

    // 45 today + 5 = 50 → allowed.
    for ($i = 0; $i < 45; $i++) {
        makeGalleryPhoto($album, $user, [
            'path_original' => "photos/{$i}_orig.webp",
            'path_medium' => "photos/{$i}_medium.webp",
            'path_thumb' => "photos/{$i}_thumb.webp",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    actingAs($user)
        ->post(route('cabinet.gallery.photos.upload', $album), [
            'photos' => makeBatch(5),
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'gallery']));

    Queue::assertPushed(ProcessPhotoJob::class, 5);
});

it('forbids uploading photos to a foreign album', function () {
    $owner = makeGalleryUser();
    $attacker = makeGalleryUser();
    $album = makePlayerAlbum($owner);

    actingAs($attacker)
        ->post(route('cabinet.gallery.photos.upload', $album), [
            'photos' => makeBatch(1),
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

// ------------------------------------------------------------------
// Photos — update
// ------------------------------------------------------------------

it('updates own photo caption', function () {
    $user = makeGalleryUser();
    $album = makePlayerAlbum($user);
    $photo = makeGalleryPhoto($album, $user);

    actingAs($user)
        ->patch(route('cabinet.gallery.photos.update', $photo), [
            'caption' => 'Новая подпись',
            'is_published' => '1',
        ])
        ->assertRedirect(route('cabinet.tab', ['tab' => 'gallery']));

    $photo->refresh();
    expect($photo->caption)->toBe('Новая подпись');
    expect($photo->is_published)->toBeTrue();
});

it('forbids updating a foreign photo', function () {
    $owner = makeGalleryUser();
    $attacker = makeGalleryUser();
    $album = makePlayerAlbum($owner);
    $photo = makeGalleryPhoto($album, $owner);

    actingAs($attacker)
        ->patch(route('cabinet.gallery.photos.update', $photo), ['caption' => 'Взлом'])
        ->assertForbidden();

    expect($photo->fresh()->caption)->not->toBe('Взлом');
});

// ------------------------------------------------------------------
// Photos — destroy
// ------------------------------------------------------------------

it('deletes own photo and removes all variants from disk', function () {
    $user = makeGalleryUser();
    $album = makePlayerAlbum($user);

    $paths = ['photos/a_orig.webp', 'photos/a_medium.webp', 'photos/a_thumb.webp'];
    foreach ($paths as $p) {
        Storage::disk('public')->put($p, 'data');
    }

    $photo = makeGalleryPhoto($album, $user, [
        'path_original' => $paths[0],
        'path_medium' => $paths[1],
        'path_thumb' => $paths[2],
    ]);

    actingAs($user)
        ->delete(route('cabinet.gallery.photos.destroy', $photo))
        ->assertRedirect(route('cabinet.tab', ['tab' => 'gallery']));

    expect(Photo::find($photo->id))->toBeNull();
    foreach ($paths as $p) {
        Storage::disk('public')->assertMissing($p);
    }
});

it('forbids deleting a foreign photo', function () {
    $owner = makeGalleryUser();
    $attacker = makeGalleryUser();
    $album = makePlayerAlbum($owner);
    $photo = makeGalleryPhoto($album, $owner);

    actingAs($attacker)
        ->delete(route('cabinet.gallery.photos.destroy', $photo))
        ->assertForbidden();

    expect(Photo::find($photo->id))->not->toBeNull();
});
