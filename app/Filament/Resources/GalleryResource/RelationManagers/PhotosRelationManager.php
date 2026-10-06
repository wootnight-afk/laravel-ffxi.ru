<?php

namespace App\Filament\Resources\GalleryResource\RelationManagers;

use App\Exceptions\ImageProcessingException;
use App\Models\Album;
use App\Services\ImageProcessor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.resources.gallery.fields.photos');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('update', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path_thumb')
                    ->label(__('filament.resources.gallery.fields.photo'))
                    ->disk('public')
                    ->height(48),
                TextColumn::make('caption')
                    ->label(__('filament.resources.gallery.fields.caption'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label(__('filament.resources.gallery.fields.sort_order'))
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label(__('filament.resources.gallery.fields.is_published'))
                    ->boolean(),
                TextColumn::make('taken_at')
                    ->label(__('filament.resources.gallery.fields.taken_at'))
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Action::make('upload')
                    ->label(__('filament.resources.gallery.fields.upload'))
                    ->schema([
                        FileUpload::make('files')
                            ->label(__('filament.resources.gallery.fields.files'))
                            ->multiple()
                            ->image()
                            ->maxSize(8192)
                            ->storeFiles(false)
                            ->required(),
                    ])
                    ->visible(fn (): bool => auth()->user()?->can('albums.manage_site') ?? false)
                    ->action(function (array $data, $livewire): void {
                        /** @var Album $album */
                        $album = $livewire->getOwnerRecord();

                        abort_unless(auth()->user()?->can('update', $album), 403);

                        $userId = (int) auth()->id();
                        $processed = 0;
                        $failed = 0;

                        foreach ($data['files'] as $file) {
                            if (! $file instanceof TemporaryUploadedFile) {
                                continue;
                            }

                            try {
                                app(ImageProcessor::class)->process($file, $album, $userId);
                                $processed++;
                            } catch (ImageProcessingException) {
                                $failed++;
                            }
                        }

                        if ($processed > 0) {
                            Notification::make()
                                ->success()
                                ->title(__('filament.resources.gallery.fields.uploaded', ['count' => $processed]))
                                ->send();
                        }

                        if ($failed > 0) {
                            Notification::make()
                                ->danger()
                                ->title(__('filament.resources.gallery.fields.upload_failed', ['count' => $failed]))
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        TextInput::make('caption')
                            ->label(__('filament.resources.gallery.fields.caption'))
                            ->maxLength(200),
                        TextInput::make('sort_order')
                            ->label(__('filament.resources.gallery.fields.sort_order'))
                            ->numeric(),
                        Toggle::make('is_published')
                            ->label(__('filament.resources.gallery.fields.is_published')),
                    ]),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => auth()->user()?->can('photos.delete_any') ?? false),
                ]),
            ]);
    }
}
