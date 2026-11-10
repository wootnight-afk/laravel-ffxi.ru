<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by Page::generateUniqueSlug() when every candidate slug is taken.
 */
class SlugGenerationException extends RuntimeException
{
    public static function forTitle(string $title): self
    {
        return new self(
            'Unable to generate a unique slug for "'.$title.'" — all candidates are taken.'
        );
    }
}
