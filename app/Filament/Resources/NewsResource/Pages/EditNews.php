<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Exceptions\ImageProcessingException;
use App\Filament\Resources\NewsResource;
use App\Models\News;
use App\Services\NewsCoverUploader;
use App\Services\NewsPublishedAtResolver;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditNews extends EditRecord
{
    protected static string $resource = NewsResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['published_at'] = app(NewsPublishedAtResolver::class)->resolve(
            $data['published_at'] ?? null,
            $this->record instanceof News ? $this->record : null,
        );

        unset($data['cover']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->processCover();
    }

    private function processCover(): void
    {
        $file = $this->data['cover'] ?? null;

        if (! $file instanceof TemporaryUploadedFile || ! $this->record instanceof News) {
            return;
        }

        try {
            app(NewsCoverUploader::class)->upload($file, $this->record);
        } catch (ImageProcessingException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();
        }
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
