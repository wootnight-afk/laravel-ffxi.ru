<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case DeletionRequested = 'deletion_requested';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::DeletionRequested => 'Запрос на удаление',
            self::Suspended => 'Приостановлен',
        };
    }
}
