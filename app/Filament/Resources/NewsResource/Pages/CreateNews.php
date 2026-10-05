<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Exceptions\ImageProcessingException;
use App\Filament\Resources\NewsResource;
use App\Models\News;
use App\Services\NewsCoverUploader;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateNews extends CreateRecord
{
    protected static string $resource = NewsResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! (auth()->user()?->isAdmin() ?? false)) {
            $data['user_id'] = auth()->id();
        }

        if (($data['status'] ?? null) === News::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        unset($data['cover']);

        return $data;
    }

    protected function afterCreate(): void
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
}
