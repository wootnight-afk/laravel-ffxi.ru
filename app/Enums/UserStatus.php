<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case DeletionRequested = 'deletion_requested';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::DeletionRequested => 'Запрос на удаление',
        };
    }
}
