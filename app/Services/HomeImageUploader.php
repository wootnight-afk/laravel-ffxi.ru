<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ImageProcessingException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Throwable;

/**
 * Processes the images of the singleton page editors (home page, news page).
 *
 * Mirrors {@see NewsCoverUploader}: the file is validated by its real MIME
 * type, re-encoded to WebP (which strips EXIF and neutralises polyglots) and
 * stored under a random name, so an uploaded file can never be executed by
 * the web server. Images are only ever scaled down, never upscaled.
 */
class HomeImageUploader
{
    public const MAX_BYTES = 4 * 1024 * 1024; // 4 MB

    public const MAX_PIXELS = 25_000_000;      // 25 Mp

    public const MIN_SIDE = 100;

    public const QUALITY = 85;

    /** Hero logo of the home page. */
    public const SLOT_HERO = 'hero';

    /** Main image of the home page. */
    public const SLOT_IMAGE = 'image';

    /** Header image of the news page. */
    public const SLOT_NEWS_PAGE = 'news_page';

    /** Optional icon of a header/footer navigation item. */
    public const SLOT_NAV = 'nav';

    /** @var array<int, string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Storage directory, file suffix and the upper bound per slot.
     *
     * The source is only downscaled to fit the box, so a small banner keeps
     * its natural size. The hero box matches the bundled asset, so replacing
     * the logo never makes the page heavier than the original.
     *
     * @var array<string, array{dir: string, suffix: string, max: array{int, int}, min?: int}>
     */
    private const SLOTS = [
        self::SLOT_HERO => [
            'dir' => 'home/hero',
            'suffix' => 'hero',
            'max' => [2172, 724],
        ],
        self::SLOT_IMAGE => [
            'dir' => 'home/image',
            'suffix' => 'image',
            'max' => [1920, 1080],
        ],
        self::SLOT_NEWS_PAGE => [
            'dir' => 'news/header',
            'suffix' => 'header',
            'max' => [1920, 1080],
        ],
        // Navigation icons are rendered at 16×16 px; a lower bound of 100 px
        // would reject every usable icon.
        self::SLOT_NAV => [
            'dir' => 'nav',
            'suffix' => 'icon',
            'max' => [400, 400],
            'min' => 16,
        ],
    ];

    public function __construct(
        private readonly ImageManager $manager,
    ) {}

    /**
     * Store an uploaded image for the given slot.
     *
     * @return array{path: string, width: int, height: int} Real dimensions of
     *                                                      the stored file (needed for the `width`/`height`
     *                                                      attributes on the front end).
     *
     * @throws ImageProcessingException
     */
    public function upload(UploadedFile $file, string $slot): array
    {
        if (! array_key_exists($slot, self::SLOTS)) {
            throw new InvalidArgumentException("Unknown page image slot: {$slot}.");
        }

        $config = self::SLOTS[$slot];
        $this->validate($file, $config['min'] ?? self::MIN_SIDE);
        [$maxWidth, $maxHeight] = $config['max'];

        $hash = Str::random(12).'-'.substr(sha1_file($file->getRealPath()) ?: '', 0, 8);
        $path = "{$config['dir']}/{$hash}_{$config['suffix']}.webp";

        $disk = Storage::disk('public');

        $width = 0;
        $height = 0;

        try {
            $image = $this->manager->decodePath($file->getRealPath());
            $image->orient();
            $image->scaleDown($maxWidth, $maxHeight);

            $width = $image->width();
            $height = $image->height();

            $disk->put($path, (string) $image->encodeUsingFileExtension('webp', quality: self::QUALITY));
        } catch (Throwable $e) {
            $disk->delete($path);

            throw new ImageProcessingException(__('site.images.errors.processing_image', ['error' => $e->getMessage()]), 0, $e);
        }

        return ['path' => $path, 'width' => $width, 'height' => $height];
    }

    /**
     * Delete a previously stored custom image. Missing files are ignored, so a
     * stale settings value can always be reset.
     */
    public function remove(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * @throws ImageProcessingException
     */
    private function validate(UploadedFile $file, int $minSide): void
    {
        if (! $file->isValid()) {
            throw new ImageProcessingException(__('site.images.errors.no_file'));
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

        if ($width < $minSide || $height < $minSide) {
            throw ImageProcessingException::tooSmall($width, $height, $minSide);
        }

        if ($width * $height > self::MAX_PIXELS) {
            throw ImageProcessingException::tooManyPixels($width * $height, self::MAX_PIXELS);
        }
    }
}
