<?php

namespace App\Services;

use App\Exceptions\ImageProcessingException;
use App\Models\Album;
use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class ImageProcessor
{
    public const MAX_BYTES = 8 * 1024 * 1024; // 8 MB
    public const MAX_PIXELS = 50_000_000;      // 50 Mp
    public const MIN_SIDE = 50;

    public const ORIGINAL_MAX_WIDTH = 2560;
    public const MEDIUM_WIDTH = 1280;
    public const THUMB_SIDE = 480;

    /** @var array<int, string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function __construct(
        private readonly ImageManager $manager,
    ) {}

    public function process(UploadedFile $file, Album $album, int $userId): Photo
    {
        $this->validate($file);

        $meta = $this->extractMeta($file);

        $hash = Str::random(16) . '-' . substr(sha1_file($file->getRealPath()) ?: '', 0, 12);
        $dir = 'photos/' . now()->format('Y/m');

        $paths = [
            'original' => "{$dir}/{$hash}_orig.webp",
            'medium' => "{$dir}/{$hash}_medium.webp",
            'thumb' => "{$dir}/{$hash}_thumb.webp",
        ];

        $disk = Storage::disk('public');

        try {
            // ORIGINAL — downscale только если больше 2560
            $image = $this->manager->decodePath($file->getRealPath());
            $image->orient();
            $image->scaleDown(width: self::ORIGINAL_MAX_WIDTH);
            $disk->put($paths['original'], (string) $image->encodeUsingFileExtension('webp', quality: 90));

            // MEDIUM — 1280
            $image = $this->manager->decodePath($file->getRealPath());
            $image->orient();
            $image->scaleDown(width: self::MEDIUM_WIDTH);
            $disk->put($paths['medium'], (string) $image->encodeUsingFileExtension('webp', quality: 80));

            // THUMB — 480x480 crop
            $image = $this->manager->decodePath($file->getRealPath());
            $image->orient();
            $image->cover(self::THUMB_SIDE, self::THUMB_SIDE);
            $disk->put($paths['thumb'], (string) $image->encodeUsingFileExtension('webp', quality: 75));
        } catch (Throwable $e) {
            $disk->delete(array_values($paths));
            throw new ImageProcessingException('Ошибка обработки изображения: ' . $e->getMessage(), 0, $e);
        }

        $sortOrder = (int) Photo::query()->where('album_id', $album->id)->max('sort_order') + 1;

        return Photo::create([
            'album_id' => $album->id,
            'user_id' => $userId,
            'path_original' => $paths['original'],
            'path_medium' => $paths['medium'],
            'path_thumb' => $paths['thumb'],
            'caption' => null,
            'taken_at' => $meta['taken_at'],
            'exif' => $meta['exif'],
            'width' => $meta['width'],
            'height' => $meta['height'],
            'size_bytes' => (int) $file->getSize(),
            'sort_order' => $sortOrder,
            'is_published' => true,
        ]);
    }

    private function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new ImageProcessingException('Файл не загружен.');
        }

        $size = (int) $file->getSize();
        if ($size > self::MAX_BYTES) {
            throw ImageProcessingException::tooLarge($size, self::MAX_BYTES);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file->getRealPath()) ?: '';

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ImageProcessingException::unsupportedFormat($mime);
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            throw ImageProcessingException::unableToRead();
        }

        [$width, $height] = $info;

        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE) {
            throw ImageProcessingException::tooSmall($width, $height, self::MIN_SIDE);
        }

        if ($width * $height > self::MAX_PIXELS) {
            throw ImageProcessingException::tooManyPixels($width * $height, self::MAX_PIXELS);
        }
    }

    /**
     * @return array{taken_at: ?string, exif: ?array, width: int, height: int}
     */
    private function extractMeta(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());
        $width = $info[0] ?? 0;
        $height = $info[1] ?? 0;

        $takenAt = null;
        $exifData = null;

        try {
            $image = $this->manager->decodePath($file->getRealPath());
            $make = $image->exif('Make');
            $model = $image->exif('Model');
            $dateTime = $image->exif('DateTimeOriginal');

            $exifData = array_filter([
                'make' => is_string($make) ? trim($make) : null,
                'model' => is_string($model) ? trim($model) : null,
            ]);

            if (is_string($dateTime) && $dateTime !== '') {
                try {
                    $takenAt = \Illuminate\Support\Carbon::createFromFormat('Y:m:d H:i:s', $dateTime)?->toDateTimeString();
                } catch (Throwable) {
                    // некорректная дата EXIF — игнорируем
                }
            }
        } catch (Throwable) {
            // EXIF недоступен — не критично
        }

        return [
            'taken_at' => $takenAt,
            'exif' => $exifData !== [] ? $exifData : null,
            'width' => (int) $width,
            'height' => (int) $height,
        ];
    }
}
