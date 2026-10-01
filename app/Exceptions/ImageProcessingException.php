<?php

namespace App\Exceptions;

use RuntimeException;

class ImageProcessingException extends RuntimeException
{
    public static function unsupportedFormat(string $mime): self
    {
        return new self("Недопустимый формат изображения: {$mime}.");
    }

    public static function tooLarge(int $bytes, int $limit): self
    {
        return new self("Файл слишком большой ({$bytes} байт, лимит {$limit}).");
    }

    public static function tooManyPixels(int $pixels, int $limit): self
    {
        return new self("Разрешение слишком большое ({$pixels} px, лимит {$limit}).");
    }

    public static function tooSmall(int $width, int $height, int $min): self
    {
        return new self("Изображение слишком маленькое ({$width}x{$height}, минимум {$min}x{$min}).");
    }

    public static function unableToRead(): self
    {
        return new self('Не удалось прочитать файл как изображение.');
    }
}
