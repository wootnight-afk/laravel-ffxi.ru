<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ImageProcessingException;
use App\Models\News;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class NewsCoverUploader
{
    public const MAX_BYTES = 4 * 1024 * 1024; // 4 MB

    public const MAX_PIXELS = 25_000_000;      // 25 Mp

    public const MIN_SIDE = 200;

    public const COVER_WIDTH = 1280;

    public const COVER_HEIGHT = 720;

    public const QUALITY = 85;

    /** @var array<int, string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly ImageManager $manager,
    ) {}

    /**
     * Process an uploaded cover image for the given news.
     *
     * Stores a single 1280x720 WebP file, updates $news->cover_path,
     * and removes the previous cover if it existed.
     *
     * @throws ImageProcessingException
     */
    public function upload(UploadedFile $file, News $news): News
    {
        $this->validate($file);

        $hash = Str::random(12).'-'.substr(sha1_file($file->getRealPath()) ?: '', 0, 8);
        $dir = 'news/covers/'.$news->user_id;
        $path = "{$dir}/{$hash}_cover.webp";

        $disk = Storage::disk('public');

        try {
            $image = $this->manager->decodePath($file->getRealPath());
            $image->orient();
            $image->cover(self::COVER_WIDTH, self::COVER_HEIGHT);

            $disk->put($path, (string) $image->encodeUsingFileExtension('webp', quality: self::QUALITY));
        } catch (Throwable $e) {
            $disk->delete($path);

            throw new ImageProcessingException('Ошибка обработки обложки: '.$e->getMessage(), 0, $e);
        }

        // Remove the previous cover if any.
        if ($news->cover_path && $disk->exists($news->cover_path)) {
            $disk->delete($news->cover_path);
        }

        $news->forceFill(['cover_path' => $path])->save();

        return $news;
    }

    public function remove(News $news): void
    {
        $disk = Storage::disk('public');

        if ($news->cover_path && $disk->exists($news->cover_path)) {
            $disk->delete($news->cover_path);
        }

        $news->forceFill(['cover_path' => null])->save();
    }

    /**
     * @throws ImageProcessingException
     */
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
}
