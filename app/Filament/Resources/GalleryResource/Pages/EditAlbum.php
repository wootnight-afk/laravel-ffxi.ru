<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Filament\Resources\GalleryResource;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditAlbum extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    /**
     * Показываем RelationManager фотографий вкладкой рядом с формой альбома
     * (штатный механизм Filament, без кастомных страниц).
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return __('filament.resources.gallery.sections.main');
    }

    public function getContentTabIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-rectangle-stack';
    }

    /**
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
