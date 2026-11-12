<?php

namespace App\Filament\Resources\GalleryResource\RelationManagers;

use App\Exceptions\ImageProcessingException;
use App\Models\Album;
use App\Models\Photo;
use App\Services\ImageProcessor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
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

    /**
     * Лимит загрузки только для админской Галереи (16 MB).
     * Личная галерея игрока продолжает использовать ImageProcessor::MAX_BYTES (8 MB).
     */
    private const ADMIN_UPLOAD_MAX_KB = 16 * 1024;

    private const ADMIN_UPLOAD_MAX_BYTES = 16 * 1024 * 1024;

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
                    ->searchable(['caption', 'caption_en']),
                TextColumn::make('sort_order')
                    ->label(__('filament.resources.gallery.fields.sort_order'))
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label(__('filament.resources.gallery.fields.is_published'))
                    ->boolean(),
                TextColumn::make('taken_at')
                    ->label(__('filament.resources.gallery.fields.taken_at'))
                    ->dateTime()
                    // ADR-011 §2.9: момент съёмки хранится и показывается в UTC.
                    ->timezone('UTC')
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
                            ->maxSize(self::ADMIN_UPLOAD_MAX_KB)
                            ->storeFiles(false)
                            ->required(),
                        Toggle::make('show_title_in_lightbox')
                            ->label(__('filament.resources.gallery.fields.show_title_in_lightbox'))
                            ->helperText(__('filament.resources.gallery.fields.show_title_in_lightbox_help'))
                            ->default(false),
                        Toggle::make('show_summary_in_lightbox')
                            ->label(__('filament.resources.gallery.fields.show_summary_in_lightbox'))
                            ->helperText(__('filament.resources.gallery.fields.show_summary_in_lightbox_help'))
                            ->default(false),
                    ])
                    ->visible(fn (): bool => auth()->user()?->can('albums.manage_site') ?? false)
                    ->action(function (array $data, $livewire): void {
                        /** @var Album $album */
                        $album = $livewire->getOwnerRecord();

                        abort_unless(auth()->user()?->can('update', $album), 403);

                        $userId = (int) auth()->id();
                        $processed = 0;
                        $failed = 0;

                        /** @var list<int> $createdIds */
                        $createdIds = [];

                        foreach ($data['files'] as $file) {
                            if (! $file instanceof TemporaryUploadedFile) {
                                continue;
                            }

                            try {
                                $photo = app(ImageProcessor::class)->process($file, $album, $userId, self::ADMIN_UPLOAD_MAX_BYTES);
                                $createdIds[] = (int) $photo->getKey();
                                $processed++;
                            } catch (ImageProcessingException) {
                                $failed++;
                            }
                        }

                        // Переключатели, выбранные при загрузке, применяются ко всей партии.
                        if ($createdIds !== []) {
                            Photo::query()->whereIn('id', $createdIds)->update([
                                'show_title_in_lightbox' => (bool) ($data['show_title_in_lightbox'] ?? false),
                                'show_summary_in_lightbox' => (bool) ($data['show_summary_in_lightbox'] ?? false),
                            ]);
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
                        TextInput::make('caption_en')
                            ->label(__('filament.resources.gallery.fields.caption_en'))
                            ->maxLength(200)
                            ->helperText(__('filament.resources.gallery.fields.caption_en_help')),
                        TextInput::make('title')
                            ->label(__('filament.resources.gallery.fields.photo_title'))
                            ->maxLength(150),
                        TextInput::make('title_en')
                            ->label(__('filament.resources.gallery.fields.photo_title_en'))
                            ->maxLength(150)
                            ->helperText(__('filament.resources.gallery.fields.photo_title_en_help')),
                        TextInput::make('summary')
                            ->label(__('filament.resources.gallery.fields.photo_summary'))
                            ->maxLength(300),
                        TextInput::make('summary_en')
                            ->label(__('filament.resources.gallery.fields.photo_summary_en'))
                            ->maxLength(300)
                            ->helperText(__('filament.resources.gallery.fields.photo_summary_en_help')),
                        Textarea::make('details')
                            ->label(__('filament.resources.gallery.fields.photo_details'))
                            ->rows(3),
                        Textarea::make('details_en')
                            ->label(__('filament.resources.gallery.fields.photo_details_en'))
                            ->rows(3)
                            ->helperText(__('filament.resources.gallery.fields.photo_details_en_help')),
                        Toggle::make('show_title_in_lightbox')
                            ->label(__('filament.resources.gallery.fields.show_title_in_lightbox'))
                            ->helperText(__('filament.resources.gallery.fields.show_title_in_lightbox_help')),
                        Toggle::make('show_summary_in_lightbox')
                            ->label(__('filament.resources.gallery.fields.show_summary_in_lightbox'))
                            ->helperText(__('filament.resources.gallery.fields.show_summary_in_lightbox_help')),
                        TextInput::make('sort_order')
                            ->label(__('filament.resources.gallery.fields.sort_order'))
                            ->numeric(),
                        Toggle::make('is_published')
                            ->label(__('filament.resources.gallery.fields.is_published')),
                    ]),
                Action::make('uploadPreview')
                    ->label(__('filament.resources.gallery.fields.upload_preview'))
                    ->icon('heroicon-o-photo')
                    ->visible(fn (): bool => auth()->user()?->can('albums.manage_site') ?? false)
                    ->schema([
                        FileUpload::make('preview')
                            ->label(__('filament.resources.gallery.fields.preview_file'))
                            ->image()
                            ->maxSize(self::ADMIN_UPLOAD_MAX_KB)
                            ->storeFiles(false)
                            ->required(),
                    ])
                    ->action(function (array $data, Photo $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        $file = $data['preview'] ?? null;

                        if (! $file instanceof TemporaryUploadedFile) {
                            return;
                        }

                        try {
                            app(ImageProcessor::class)->processPreview($file, $record);

                            Notification::make()
                                ->success()
                                ->title(__('filament.resources.gallery.fields.preview_uploaded'))
                                ->send();
                        } catch (ImageProcessingException $e) {
                            Notification::make()
                                ->danger()
                                ->title($e->getMessage())
                                ->send();
                        }
                    }),
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
