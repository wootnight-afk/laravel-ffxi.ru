<?php

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function lightboxJpeg(int $width = 800, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 90, 140, 190));

    $path = tempnam(sys_get_temp_dir(), 'lightbox_').'.jpg';
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return $path;
}

function lightboxAlbum(User $admin, array $overrides = []): Album
{
    return Album::create(array_merge([
        'user_id' => $admin->id,
        'scope' => Album::SCOPE_SITE,
        'title' => 'Lightbox Album '.uniqid(),
        'slug' => 'lightbox-album-'.uniqid(),
        'is_published' => true,
    ], $overrides));
}

function lightboxPhoto(Album $album, User $admin, array $overrides = []): Photo
{
    return Photo::create(array_merge([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/2026/11/orig.webp',
        'path_medium' => 'photos/2026/11/medium.webp',
        'path_thumb' => 'photos/2026/11/thumb.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    Storage::fake('public');
});

it('falls back from preview to thumb to medium', function () {
    $admin = User::factory()->create();
    $album = lightboxAlbum($admin);

    $photo = lightboxPhoto($album, $admin);
    expect($photo->urlThumb())->toContain('thumb.webp');

    $photo->forceFill(['path_preview' => 'photos/2026/11/preview.webp'])->save();
    expect($photo->refresh()->urlThumb())->toContain('preview.webp');

    $photo->forceFill(['path_preview' => null, 'path_thumb' => null])->save();
    expect($photo->refresh()->urlThumb())->toContain('medium.webp');
});

it('renders lightbox title and summary only when the switch is on and the field is filled', function () {
    $admin = User::factory()->create();
    $album = lightboxAlbum($admin);

    lightboxPhoto($album, $admin, [
        'title' => 'Название фото',
        'summary' => 'Краткое описание',
        'show_title_in_lightbox' => true,
        'show_summary_in_lightbox' => true,
    ]);

    lightboxPhoto($album, $admin, [
        'show_title_in_lightbox' => true,
        'show_summary_in_lightbox' => true,
    ]);

    $response = $this->get(route('gallery.album', $album->slug));

    $response->assertOk();
    $response->assertSee('data-pswp-title="Название фото"', false);
    $response->assertSee('data-pswp-summary="Краткое описание"', false);
    // Второе фото — переключатели включены, но поля пусты: атрибутов нет.
    expect(substr_count($response->getContent(), 'data-pswp-title'))->toBe(1);
    expect(substr_count($response->getContent(), 'data-pswp-summary'))->toBe(1);
});

it('hides lightbox attribute when the switch is off', function () {
    $admin = User::factory()->create();
    $album = lightboxAlbum($admin);

    lightboxPhoto($album, $admin, [
        'title' => 'Название фото',
        'summary' => 'Краткое описание',
        'show_title_in_lightbox' => false,
        'show_summary_in_lightbox' => false,
    ]);

    $response = $this->get(route('gallery.album', $album->slug));

    $response->assertOk();
    $response->assertDontSee('data-pswp-title', false);
    $response->assertDontSee('data-pswp-summary', false);
});

it('shows the additional description on the photo page without duplicating the summary', function () {
    $admin = User::factory()->create();
    $album = lightboxAlbum($admin);

    $photo = lightboxPhoto($album, $admin, [
        'summary' => 'Краткое описание',
        'details' => 'Дополнительное описание',
    ]);

    $response = $this->get(route('gallery.photo', ['album' => $album->slug, 'photo' => $photo->id]));

    $response->assertOk();
    $response->assertSee('Дополнительное описание');
    $response->assertDontSee('Краткое описание');
});

it('stores an optimized preview as webp and keeps the original photo untouched', function () {
    $admin = User::factory()->create();
    $album = lightboxAlbum($admin);
    $photo = lightboxPhoto($album, $admin);

    $path = lightboxJpeg(1600, 900);
    $file = new UploadedFile($path, 'preview.jpg', 'image/jpeg', null, true);

    app(ImageProcessor::class)->processPreview($file, $photo);

    $photo->refresh();

    expect($photo->path_preview)->toEndWith('_preview.webp');
    expect($photo->path_original)->toBe('photos/2026/11/orig.webp');

    Storage::disk('public')->assertExists($photo->path_preview);

    @unlink($path);
});

it('keeps a 4k photo at full width in the original variant', function () {
    $admin = User::factory()->create();
    $album = lightboxAlbum($admin);

    $path = lightboxJpeg(3840, 2160);
    $file = new UploadedFile($path, '4k.jpg', 'image/jpeg', null, true);

    $photo = app(ImageProcessor::class)->process($file, $album, $admin->id);

    $stored = getimagesizefromstring(Storage::disk('public')->get($photo->path_original));

    expect($stored[0])->toBe(3840);
    expect($photo->width)->toBe(3840);
    expect($photo->height)->toBe(2160);

    @unlink($path);
});
