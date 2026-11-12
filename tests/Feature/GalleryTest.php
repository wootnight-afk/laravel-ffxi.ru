<?php

use App\Exceptions\ImageProcessingException;
use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeJpeg(int $width = 800, int $height = 600, int $bytes = 0): string
{
    $image = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($image, 100, 150, 200);
    imagefill($image, 0, 0, $color);

    $path = tempnam(sys_get_temp_dir(), 'test_').'.jpg';
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    if ($bytes > 0 && filesize($path) < $bytes) {
        // Наращиваем комментарием в EXIF-совместимом сегменте
        $data = file_get_contents($path);
        $data .= str_repeat("\xFF\xFE\x00\x10".str_repeat('x', 14), (int) ceil(($bytes - strlen($data)) / 18));
        file_put_contents($path, $data);
    }

    return $path;
}

function makeUploaded(string $path, string $mime = 'image/jpeg'): UploadedFile
{
    return new UploadedFile($path, basename($path), $mime, null, true);
}

function makeAlbum(User $admin, array $overrides = []): Album
{
    return Album::create(array_merge([
        'user_id' => $admin->id,
        'scope' => Album::SCOPE_SITE,
        'title' => 'Test Album '.uniqid(),
        'slug' => 'test-album-'.uniqid(),
        'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

it('processes a jpeg into three webp variants', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    $path = makeJpeg(1200, 900);
    $file = makeUploaded($path);

    $processor = app(ImageProcessor::class);
    $photo = $processor->process($file, $album, $admin->id);

    expect($photo->path_original)->toEndWith('_orig.webp');
    expect($photo->path_medium)->toEndWith('_medium.webp');
    expect($photo->path_thumb)->toEndWith('_thumb.webp');
    expect($photo->width)->toBe(1200);
    expect($photo->height)->toBe(900);

    Storage::disk('public')->assertExists($photo->path_original);
    Storage::disk('public')->assertExists($photo->path_medium);
    Storage::disk('public')->assertExists($photo->path_thumb);

    @unlink($path);
});

it('rejects files larger than 8MB', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    $path = makeJpeg(200, 200);
    $file = makeUploaded($path);

    // Подменяем размер через мок — 9 MB
    $fileMock = Mockery::mock(UploadedFile::class);
    $fileMock->shouldReceive('isValid')->andReturn(true);
    $fileMock->shouldReceive('getSize')->andReturn(9 * 1024 * 1024);
    $fileMock->shouldReceive('getRealPath')->andReturn($path);

    $processor = app(ImageProcessor::class);

    expect(fn () => $processor->process($fileMock, $album, $admin->id))
        ->toThrow(ImageProcessingException::class);

    @unlink($path);
});

it('rejects non-image files', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    $path = tempnam(sys_get_temp_dir(), 'txt_').'.txt';
    file_put_contents($path, 'not an image');

    $file = makeUploaded($path, 'text/plain');

    $processor = app(ImageProcessor::class);

    expect(fn () => $processor->process($file, $album, $admin->id))
        ->toThrow(ImageProcessingException::class);

    @unlink($path);
});

it('rejects images smaller than 50x50', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    $path = makeJpeg(20, 20);
    $file = makeUploaded($path);

    $processor = app(ImageProcessor::class);

    expect(fn () => $processor->process($file, $album, $admin->id))
        ->toThrow(ImageProcessingException::class);

    @unlink($path);
});

it('shows published site albums on the gallery index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $publicAlbum = makeAlbum($admin, ['title' => 'Public Album', 'is_published' => true]);
    $privateAlbum = makeAlbum($admin, ['title' => 'Private Album', 'is_published' => false]);

    $response = $this->get(route('gallery.index'));

    $response->assertOk();
    $response->assertSee('Public Album');
    $response->assertDontSee('Private Album');
});

it('returns 404 for unpublished album', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin, ['is_published' => false]);

    $this->get(route('gallery.album', $album->slug))->assertNotFound();
});

it('shows published photos of an album', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    $photo = Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/2026/10/test_orig.webp',
        'path_medium' => 'photos/2026/10/test_medium.webp',
        'path_thumb' => 'photos/2026/10/test_thumb.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $response = $this->get(route('gallery.album', $album->slug));

    $response->assertOk();
    $response->assertSee($photo->urlThumb());
});

it('returns 404 for photo in another album', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $albumA = makeAlbum($admin);
    $albumB = makeAlbum($admin);

    $photo = Photo::create([
        'album_id' => $albumA->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/test_orig.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $this->get(route('gallery.photo', [$albumB->slug, $photo->id]))
        ->assertNotFound();
});

it('falls back to the russian album title on the english locale', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $album = makeAlbum($admin, ['title' => 'Русский альбом', 'title_en' => null]);

    $viewer = User::factory()->create(['email_verified_at' => now(), 'locale' => 'en']);
    $viewer->assignRole('user');

    $this->actingAs($viewer)
        ->get(route('gallery.album', $album->slug))
        ->assertOk()
        ->assertSee('Русский альбом');
});

it('uses the english album title when it is filled', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $album = makeAlbum($admin, ['title' => 'Русский альбом', 'title_en' => 'English album']);

    $viewer = User::factory()->create(['email_verified_at' => now(), 'locale' => 'en']);
    $viewer->assignRole('user');

    $this->actingAs($viewer)
        ->get(route('gallery.album', $album->slug))
        ->assertOk()
        ->assertSee('English album')
        ->assertDontSee('Русский альбом');
});

it('renders photoswipe attributes and keeps the gallery markup', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/pswp_orig.webp',
        'path_medium' => 'photos/pswp_medium.webp',
        'path_thumb' => 'photos/pswp_thumb.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $this->get(route('gallery.album', $album->slug))
        ->assertOk()
        ->assertSee('class="gallery-grid"', false)
        ->assertSee('class="gallery-thumb"', false)
        ->assertSee('data-pswp-width="800"', false)
        ->assertSee('data-pswp-height="600"', false)
        ->assertSee('data-photo-url=', false);
});

it('links an overview thumbnail to its album with the clicked photo deep link', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    $photo = Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/deep_orig.webp',
        'path_medium' => 'photos/deep_medium.webp',
        'path_thumb' => 'photos/deep_thumb.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $this->get(route('gallery.index'))
        ->assertOk()
        ->assertSee(route('gallery.album', ['album' => $album->slug, 'photo' => $photo->id]), false);
});

it('exposes the photo id on album slides for the deep link handoff', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    $photo = Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/deep2_orig.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $this->get(route('gallery.album', $album->slug))
        ->assertOk()
        ->assertSee('data-photo-id="'.$photo->id.'"', false);
});

it('keeps the album page working when the deep link photo is unknown', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/deep3_orig.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $this->get(route('gallery.album', ['album' => $album->slug, 'photo' => 999999]))
        ->assertOk()
        ->assertSee('class="gallery-grid"', false);
});

it('uses the english album title on the gallery overview', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin, ['title' => 'Русский обзорный альбом', 'title_en' => 'English overview album']);

    Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/overview_orig.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $viewer = User::factory()->create(['email_verified_at' => now(), 'locale' => 'en']);
    $viewer->assignRole('user');

    $this->actingAs($viewer)
        ->get(route('gallery.index'))
        ->assertOk()
        ->assertSee('English overview album')
        ->assertDontSee('Русский обзорный альбом');
});

it('falls back to the russian album title on the gallery overview', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin, ['title' => 'Русский обзорный альбом', 'title_en' => null]);

    Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/overview2_orig.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $viewer = User::factory()->create(['email_verified_at' => now(), 'locale' => 'en']);
    $viewer->assignRole('user');

    $this->actingAs($viewer)
        ->get(route('gallery.index'))
        ->assertOk()
        ->assertSee('Русский обзорный альбом');
});

it('localizes the lightbox details button label', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $album = makeAlbum($admin);

    Photo::create([
        'album_id' => $album->id,
        'user_id' => $admin->id,
        'path_original' => 'photos/label_orig.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ]);

    $this->get(route('gallery.album', $album->slug))
        ->assertOk()
        ->assertSee('data-details-label="Подробнее"', false);

    $viewer = User::factory()->create(['email_verified_at' => now(), 'locale' => 'en']);
    $viewer->assignRole('user');

    $this->actingAs($viewer)
        ->get(route('gallery.album', $album->slug))
        ->assertOk()
        ->assertSee('data-details-label="Details"', false);
});
