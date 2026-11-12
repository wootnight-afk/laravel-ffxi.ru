<?php

use App\Models\Album;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\User;

function commentAuthor(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    return $user;
}

function siteAlbumWithPhoto(User $owner, array $photoOverrides = []): array
{
    $album = Album::create([
        'user_id' => $owner->id,
        'scope' => Album::SCOPE_SITE,
        'title' => 'Site album '.uniqid(),
        'slug' => 'site-album-'.uniqid(),
        'is_published' => true,
    ]);

    $photo = Photo::create(array_merge([
        'album_id' => $album->id,
        'user_id' => $owner->id,
        'path_original' => 'photos/'.uniqid().'_orig.webp',
        'path_medium' => 'photos/'.uniqid().'_medium.webp',
        'path_thumb' => 'photos/'.uniqid().'_thumb.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ], $photoOverrides));

    return [$album, $photo];
}

function playerAlbumWithPhoto(User $owner, array $photoOverrides = []): array
{
    $owner->forceFill(['is_profile_public' => true, 'is_gallery_public' => true])->save();

    $album = Album::create([
        'user_id' => $owner->id,
        'scope' => Album::SCOPE_PLAYER,
        'title' => 'Player album '.uniqid(),
        'slug' => 'player-album-'.uniqid(),
        'is_published' => true,
    ]);

    $photo = Photo::create(array_merge([
        'album_id' => $album->id,
        'user_id' => $owner->id,
        'path_original' => 'photos/'.uniqid().'_orig.webp',
        'path_medium' => 'photos/'.uniqid().'_medium.webp',
        'path_thumb' => 'photos/'.uniqid().'_thumb.webp',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 1000,
        'is_published' => true,
    ], $photoOverrides));

    return [$album, $photo];
}

it('creates a comment attached to a site photo', function () {
    $author = commentAuthor();
    [$album, $photo] = siteAlbumWithPhoto($author);

    $this->actingAs($author)
        ->postJson(route('gallery.photo.comments.store', ['album' => $album->slug, 'photo' => $photo->id]), [
            'body' => str_repeat('Комментарий к фото. ', 3),
        ])
        ->assertCreated();

    $comment = Comment::query()->firstOrFail();

    expect($comment->commentable_type)->toBe(Photo::class)
        ->and($comment->commentable_id)->toBe($photo->id);
});

it('creates a comment attached to a player photo', function () {
    $author = commentAuthor();
    [$album, $photo] = playerAlbumWithPhoto($author);

    $this->actingAs($author)
        ->postJson(route('gallery.player.comments.store', [
            'user' => $author->name,
            'album' => $album->slug,
            'photo' => $photo->id,
        ]), [
            'body' => str_repeat('Комментарий к фото. ', 3),
        ])
        ->assertCreated();

    $comment = Comment::query()->firstOrFail();

    expect($comment->commentable_type)->toBe(Photo::class)
        ->and($comment->commentable_id)->toBe($photo->id);
});

it('attaches a reply to the same photo', function () {
    $author = commentAuthor();
    [$album, $photo] = siteAlbumWithPhoto($author);

    $parent = Comment::create([
        'user_id' => $author->id,
        'commentable_type' => Photo::class,
        'commentable_id' => $photo->id,
        'body' => str_repeat('Родительский комментарий. ', 3),
        'status' => Comment::STATUS_APPROVED,
    ]);

    $this->actingAs($author)
        ->postJson(route('gallery.photo.comments.store', ['album' => $album->slug, 'photo' => $photo->id]), [
            'body' => str_repeat('Ответ на комментарий. ', 3),
            'parent_id' => $parent->id,
        ])
        ->assertCreated();

    $reply = Comment::query()->whereNotNull('parent_id')->firstOrFail();

    expect($reply->parent_id)->toBe($parent->id)
        ->and($reply->commentable_id)->toBe($photo->id);
});

it('rejects a reply to a comment of another photo', function () {
    $author = commentAuthor();
    [$album, $photo] = siteAlbumWithPhoto($author);
    [, $otherPhoto] = siteAlbumWithPhoto($author);

    $foreignParent = Comment::create([
        'user_id' => $author->id,
        'commentable_type' => Photo::class,
        'commentable_id' => $otherPhoto->id,
        'body' => str_repeat('Комментарий к другому фото. ', 3),
        'status' => Comment::STATUS_APPROVED,
    ]);

    $this->actingAs($author)
        ->postJson(route('gallery.photo.comments.store', ['album' => $album->slug, 'photo' => $photo->id]), [
            'body' => str_repeat('Ответ не туда. ', 3),
            'parent_id' => $foreignParent->id,
        ])
        ->assertStatus(422);
});

it('rejects a comment for a photo of another album', function () {
    $author = commentAuthor();
    [$album] = siteAlbumWithPhoto($author);
    [, $foreignPhoto] = siteAlbumWithPhoto($author);

    $this->actingAs($author)
        ->postJson(route('gallery.photo.comments.store', ['album' => $album->slug, 'photo' => $foreignPhoto->id]), [
            'body' => str_repeat('Подмена альбома. ', 3),
        ])
        ->assertNotFound();
});

it('requires a verified email to comment on a photo', function () {
    $unverified = User::factory()->create(['email_verified_at' => null]);
    $unverified->assignRole('user');

    [$album, $photo] = siteAlbumWithPhoto($unverified);

    $this->actingAs($unverified)
        ->postJson(route('gallery.photo.comments.store', ['album' => $album->slug, 'photo' => $photo->id]), [
            'body' => str_repeat('Без подтверждения. ', 3),
        ])
        ->assertForbidden();
});
