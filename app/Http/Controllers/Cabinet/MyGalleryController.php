<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use App\Http\Requests\UploadPhotosRequest;
use App\Jobs\ProcessPhotoJob;
use App\Models\Album;
use App\Models\Photo;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MyGalleryController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function store(StoreAlbumRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $limit = $this->settings->int('gallery_max_albums_per_user', 10);
        $current = Album::query()
            ->where('user_id', $user->id)
            ->where('scope', Album::SCOPE_PLAYER)
            ->count();

        if ($current >= $limit) {
            return back()->withErrors([
                'title' => "Достигнут лимит альбомов: {$limit}.",
            ]);
        }

        $album = Album::create([
            'user_id' => $user->id,
            'scope' => Album::SCOPE_PLAYER,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? true),
            'sort_order' => (int) Album::query()->where('user_id', $user->id)->max('sort_order') + 1,
        ]);

        return redirect()
            ->route('cabinet.tab', ['tab' => 'gallery'])
            ->with('status', 'Альбом создан.')
            ->with('open_album_id', $album->id);
    }

    public function update(UpdateAlbumRequest $request, Album $album): RedirectResponse
    {
        Gate::authorize('update', $album);

        $data = $request->validated();

        $album->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? $album->is_published),
        ])->save();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'gallery'])
            ->with('status', 'Альбом обновлён.')
            ->with('open_album_id', $album->id);
    }

    public function destroy(Album $album): RedirectResponse
    {
        Gate::authorize('delete', $album);

        // Soft-delete album and all its photos.
        // TODO(stage-11): implement a cleanup job that removes the associated
        // files (orig/medium/thumb) for soft-deleted photos.
        DB::transaction(function () use ($album): void {
            $album->photos()->delete();
            $album->delete();
        });

        return redirect()
            ->route('cabinet.tab', ['tab' => 'gallery'])
            ->with('status', 'Альбом удалён.');
    }

    public function upload(UploadPhotosRequest $request, Album $album): RedirectResponse
    {
        Gate::authorize('update', $album);

        $user = $request->user();
        $files = $request->file('photos', []);

        if (! is_array($files) || $files === []) {
            return back()->withErrors(['photos' => 'Выберите файлы для загрузки.']);
        }

        $dailyLimit = $this->settings->int('gallery_max_photos_per_day', 50);
        $today = Photo::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        $requested = count($files);

        if ($today + $requested > $dailyLimit) {
            return back()->withErrors([
                'photos' => 'Сегодня можно загрузить ещё '.max(0, $dailyLimit - $today).'.',
            ]);
        }

        // Persist to the local disk first so the queue job can pick it up later.
        foreach ($files as $file) {
            /** @var UploadedFile $file */
            $tempPath = 'tmp/photos/'.Str::uuid().'.'.$file->getClientOriginalExtension();

            Storage::disk('local')->put($tempPath, $file->get());

            ProcessPhotoJob::dispatch($tempPath, $album->id, $user->id);
        }

        return redirect()
            ->route('cabinet.tab', ['tab' => 'gallery'])
            ->with('status', 'Загрузка запущена: '.$requested.' файл(ов). Обработка займёт несколько секунд.')
            ->with('open_album_id', $album->id);
    }
}
