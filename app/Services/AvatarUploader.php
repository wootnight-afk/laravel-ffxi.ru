<?php

namespace App\Services;

use App\Exceptions\ImageProcessingException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class AvatarUploader
{
    public const MAX_BYTES = 4 * 1024 * 1024; // 4 MB

    public const MAX_PIXELS = 25_000_000;      // 25 Mp

    public const MIN_SIDE = 50;

    public const FULL_SIDE = 256;

    public const THUMB_SIDE = 64;

    /** @var array<int, string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly ImageManager $manager,
    ) {}

    public function upload(UploadedFile $file, User $user): User
    {
        $this->validate($file);

        $hash = Str::random(12).'-'.substr(sha1_file($file->getRealPath()) ?: '', 0, 8);
        $dir = 'avatars/'.$user->id;

        $fullPath = "{$dir}/{$hash}_full.webp";
        $thumbPath = "{$dir}/{$hash}_thumb.webp";

        $disk = Storage::disk('public');

        try {
            $image = $this->manager->decodePath($file->getRealPath());
            $image->orient();

            // FULL — 256x256
            $full = $this->manager->decodePath($file->getRealPath());
            $full->orient();
            $full->cover(self::FULL_SIDE, self::FULL_SIDE);
            $disk->put($fullPath, (string) $full->encodeUsingFileExtension('webp', quality: 88));

            // THUMB — 64x64
            $thumb = $this->manager->decodePath($file->getRealPath());
            $thumb->orient();
            $thumb->cover(self::THUMB_SIDE, self::THUMB_SIDE);
            $disk->put($thumbPath, (string) $thumb->encodeUsingFileExtension('webp', quality: 80));
        } catch (Throwable $e) {
            $disk->delete([$fullPath, $thumbPath]);
            throw new ImageProcessingException('Ошибка обработки аватара: '.$e->getMessage(), 0, $e);
        }

        // Удаляем старый аватар
        if ($user->avatar_path && $disk->exists($user->avatar_path)) {
            $disk->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => $fullPath])->save();

        return $user;
    }

    public function remove(User $user): void
    {
        $disk = Storage::disk('public');

        if ($user->avatar_path && $disk->exists($user->avatar_path)) {
            $disk->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => null])->save();
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
}
