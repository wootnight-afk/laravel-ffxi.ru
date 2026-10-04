<?php

namespace App\Jobs;

use App\Events\PhotoPublishedForActivity;
use App\Models\Album;
use App\Services\ImageProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessPhotoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 55;

    public function __construct(
        public readonly string $tempPath,
        public readonly int $albumId,
        public readonly int $userId,
    ) {}

    public function handle(ImageProcessor $processor): void
    {
        $album = Album::query()->find($this->albumId);
        if ($album === null) {
            $this->cleanup();

            return;
        }

        $absolutePath = Storage::disk('local')->path($this->tempPath);

        if (! is_file($absolutePath)) {
            Log::warning('ProcessPhotoJob: temp file missing', ['path' => $absolutePath]);

            return;
        }

        $uploadedFile = new UploadedFile(
            $absolutePath,
            basename($absolutePath),
            null,
            null,
            true, // test mode — не перемещает файл
        );

        try {
            $photo = $processor->process($uploadedFile, $album, $this->userId);
            $actor = $photo->user;

            if ($photo->is_published && $actor !== null) {
                DB::afterCommit(fn () => event(new PhotoPublishedForActivity($photo, $actor)));
            }
        } finally {
            $this->cleanup();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessPhotoJob failed', [
            'album_id' => $this->albumId,
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
        ]);

        $this->cleanup();
    }

    private function cleanup(): void
    {
        if (Storage::disk('local')->exists($this->tempPath)) {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
