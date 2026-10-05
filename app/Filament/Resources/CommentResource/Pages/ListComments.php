<?php

namespace App\Filament\Resources\CommentResource\Pages;

use App\Filament\Resources\CommentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListComments extends ListRecords
{
    protected static string $resource = CommentResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
