<?php

namespace App\Exceptions;

use RuntimeException;

class ImageProcessingException extends RuntimeException
{
    public static function unsupportedFormat(string $mime): self
    {
        return new self((string) __('site.images.errors.unsupported_format', ['mime' => $mime]));
    }

    public static function tooLarge(int $bytes, int $limit): self
    {
        return new self((string) __('site.images.errors.too_large', [
            'bytes' => $bytes,
            'limit' => $limit,
        ]));
    }

    public static function tooManyPixels(int $pixels, int $limit): self
    {
        return new self((string) __('site.images.errors.too_many_pixels', [
            'pixels' => $pixels,
            'limit' => $limit,
        ]));
    }

    public static function tooSmall(int $width, int $height, int $min): self
    {
        return new self((string) __('site.images.errors.too_small', [
            'width' => $width,
            'height' => $height,
            'min' => $min,
        ]));
    }

    public static function unableToRead(): self
    {
        return new self((string) __('site.images.errors.unable_to_read'));
    }
}
