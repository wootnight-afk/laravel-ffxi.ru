<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Hard deletion remains unavailable until the re-authenticated E8 flow.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
