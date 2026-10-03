<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePhotoRequest;
use App\Models\Photo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MyGalleryPhotoController extends Controller
{
    public function update(UpdatePhotoRequest $request, Photo $photo): RedirectResponse
    {
        Gate::authorize('update', $photo);

        $data = $request->validated();

        $photo->fill([
            'caption' => $data['caption'] ?? null,
            'is_published' => array_key_exists('is_published', $data)
                ? (bool) $data['is_published']
                : $photo->is_published,
        ])->save();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'gallery'])
            ->with('status', 'Фото обновлено.')
            ->with('open_album_id', $photo->album_id);
    }

    public function destroy(Photo $photo): RedirectResponse
    {
        Gate::authorize('delete', $photo);

        $albumId = $photo->album_id;

        // Remove all variants from disk.
        $disk = Storage::disk('public');
        foreach ([$photo->path_original, $photo->path_medium, $photo->path_thumb] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $photo->delete();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'gallery'])
            ->with('status', 'Фото удалено.')
            ->with('open_album_id', $albumId);
    }
}
