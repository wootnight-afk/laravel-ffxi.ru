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
            self::Active => __('filament.user_status.active'),
            self::DeletionRequested => __('filament.user_status.deletion_requested'),
            self::Suspended => __('filament.user_status.suspended'),
        };
    }
}
