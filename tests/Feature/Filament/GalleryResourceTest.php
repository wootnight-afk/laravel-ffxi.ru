<?php

declare(strict_types=1);

use App\Filament\Resources\GalleryResource\Pages\CreateAlbum;
use App\Filament\Resources\GalleryResource\Pages\EditAlbum;
use App\Filament\Resources\GalleryResource\Pages\ListAlbums;
use App\Filament\Resources\GalleryResource\RelationManagers\PhotosRelationManager;
use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makeGalleryPanelUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function makeAlbumRecord(User $owner, array $overrides = []): Album
{
    return Album::create(array_merge([
        'user_id' => $owner->id,
        'scope' => Album::SCOPE_SITE,
        'title' => 'Album '.uniqid(),
        'slug' => 'album-'.uniqid(),
        'is_published' => true,
    ], $overrides));
}

function makePhotoRecord(Album $album, User $owner, array $overrides = []): Photo
{
    return Photo::create(array_merge([
        'album_id' => $album->id,
        'user_id' => $owner->id,
        'path_original' => 'photos/'.uniqid().'_orig.webp',
        'path_medium' => 'photos/'.uniqid().'_medium.webp',
        'path_thumb' => 'photos/'.uniqid().'_thumb.webp',
        'is_published' => true,
    ], $overrides));
}

// ------------------------------------------------------------------
// Access
// ------------------------------------------------------------------

it('allows editors to access the gallery resource', function () {
    $editor = makeGalleryPanelUser('editor');

    $this->actingAs($editor)->get('/admin/galleries')->assertOk();
});

it('denies regular users access to the gallery resource', function () {
    $user = makeGalleryPanelUser('user');

    $this->actingAs($user)->get('/admin/galleries')->assertForbidden();
});

// ------------------------------------------------------------------
// Album CRUD
// ------------------------------------------------------------------

it('creates a site album and generates a slug automatically', function () {
    $editor = makeGalleryPanelUser('editor');

    Livewire::actingAs($editor)
        ->test(CreateAlbum::class)
        ->set('data.title', 'Новый альбом')
        ->set('data.scope', Album::SCOPE_SITE)
        ->set('data.is_published', true)
        ->call('create')
        ->assertHasNoErrors();

    $album = Album::query()->latest('id')->firstOrFail();

    expect($album->title)->toBe('Новый альбом')
        ->and($album->slug)->not->toBeEmpty()
        ->and($album->scope)->toBe(Album::SCOPE_SITE)
        ->and($album->user_id)->toBe($editor->id);
});

it('updates album metadata through the edit page', function () {
    $editor = makeGalleryPanelUser('editor');
    $album = makeAlbumRecord($editor, ['is_published' => false]);

    Livewire::actingAs($editor)
        ->test(EditAlbum::class, ['record' => $album->getKey()])
        ->set('data.title', 'Обновлённый альбом')
        ->set('data.is_published', true)
        ->call('save')
        ->assertHasNoErrors();

    $album->refresh();

    expect($album->title)->toBe('Обновлённый альбом')
        ->and($album->is_published)->toBeTrue();
});

// ------------------------------------------------------------------
// Publication
// ------------------------------------------------------------------

it('publishes and unpublishes an album through table actions', function () {
    $editor = makeGalleryPanelUser('editor');
    $album = makeAlbumRecord($editor, ['is_published' => false]);

    Livewire::actingAs($editor)
        ->test(ListAlbums::class)
        ->callTableAction('publish', $album)
        ->assertHasNoTableActionErrors();

    expect($album->refresh()->is_published)->toBeTrue();

    Livewire::actingAs($editor)
        ->test(ListAlbums::class)
        ->callTableAction('unpublish', $album)
        ->assertHasNoTableActionErrors();

    expect($album->refresh()->is_published)->toBeFalse();
});

it('filters albums by scope', function () {
    $editor = makeGalleryPanelUser('editor');
    $site = makeAlbumRecord($editor, ['scope' => Album::SCOPE_SITE]);
    $player = makeAlbumRecord($editor, ['scope' => Album::SCOPE_PLAYER]);

    Livewire::actingAs($editor)
        ->test(ListAlbums::class)
        ->filterTable('scope', Album::SCOPE_SITE)
        ->assertCanSeeTableRecords([$site])
        ->assertCanNotSeeTableRecords([$player]);
});

// ------------------------------------------------------------------
// Photos relation manager
// ------------------------------------------------------------------

it('lists photos of an album in the relation manager', function () {
    $editor = makeGalleryPanelUser('editor');
    $album = makeAlbumRecord($editor);
    $photo = makePhotoRecord($album, $editor);

    Livewire::actingAs($editor)
        ->test(PhotosRelationManager::class, [
            'ownerRecord' => $album,
            'pageClass' => EditAlbum::class,
        ])
        ->assertCanSeeTableRecords([$photo]);
});

it('uploads a photo through the relation manager using the image processor', function () {
    Storage::fake('public');

    $admin = makeGalleryPanelUser('admin');
    $album = makeAlbumRecord($admin);

    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    Livewire::actingAs($admin)
        ->test(PhotosRelationManager::class, [
            'ownerRecord' => $album,
            'pageClass' => EditAlbum::class,
        ])
        ->mountTableAction('upload')
        ->set('mountedActions.0.data.files', [$file])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $photo = Photo::query()->where('album_id', $album->id)->first();

    expect($photo)->not->toBeNull()
        ->and($photo->path_original)->toEndWith('_orig.webp');

    Storage::disk('public')->assertExists($photo->path_original);
});

it('deletes a photo through the relation manager', function () {
    Storage::fake('public');

    $editor = makeGalleryPanelUser('editor');
    $album = makeAlbumRecord($editor);
    $photo = makePhotoRecord($album, $editor);

    Livewire::actingAs($editor)
        ->test(PhotosRelationManager::class, [
            'ownerRecord' => $album,
            'pageClass' => EditAlbum::class,
        ])
        ->callTableAction('delete', $photo)
        ->assertHasNoTableActionErrors();

    expect(Photo::query()->whereKey($photo->id)->exists())->toBeFalse();
});

// ------------------------------------------------------------------
// Export exclusion (R7)
// ------------------------------------------------------------------

it('does not expose an export action in the gallery resource', function () {
    $editor = makeGalleryPanelUser('editor');

    Livewire::actingAs($editor)
        ->test(ListAlbums::class)
        ->assertTableActionDoesNotExist('export')
        ->assertTableBulkActionDoesNotExist('export');
});
