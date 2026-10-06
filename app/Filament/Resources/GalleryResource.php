<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryResource\Pages\CreateAlbum;
use App\Filament\Resources\GalleryResource\Pages\EditAlbum;
use App\Filament\Resources\GalleryResource\Pages\ListAlbums;
use App\Filament\Resources\GalleryResource\RelationManagers\PhotosRelationManager;
use App\Models\Album;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class GalleryResource extends Resource
{
    protected static ?string $model = Album::class;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.gallery.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.gallery.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.gallery.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.resources.gallery.sections.main'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('filament.resources.gallery.fields.title'))
                        ->required()
                        ->maxLength(120),
                    TextInput::make('slug')
                        ->label(__('filament.resources.gallery.fields.slug'))
                        ->maxLength(150)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('filament.resources.gallery.fields.slug_help')),
                    Textarea::make('description')
                        ->label(__('filament.resources.gallery.fields.description'))
                        ->maxLength(2000)
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('filament.resources.gallery.sections.meta'))
                ->schema([
                    Select::make('scope')
                        ->label(__('filament.resources.gallery.fields.scope'))
                        ->options([
                            Album::SCOPE_SITE => __('filament.resources.gallery.scopes.site'),
                            Album::SCOPE_PLAYER => __('filament.resources.gallery.scopes.player'),
                        ])
                        ->required()
                        ->default(Album::SCOPE_SITE),
                    Select::make('cover_photo_id')
                        ->label(__('filament.resources.gallery.fields.cover_photo_id'))
                        ->relationship('coverPhoto', 'id')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    TextInput::make('sort_order')
                        ->label(__('filament.resources.gallery.fields.sort_order'))
                        ->numeric()
                        ->default(0),
                    Toggle::make('is_published')
                        ->label(__('filament.resources.gallery.fields.is_published'))
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.resources.gallery.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('slug')
                    ->label(__('filament.resources.gallery.fields.slug'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('scope')
                    ->label(__('filament.resources.gallery.fields.scope'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("filament.resources.gallery.scopes.{$state}")),
                TextColumn::make('photos_count')
                    ->label(__('filament.resources.gallery.fields.photos'))
                    ->counts('photos'),
                TextColumn::make('sort_order')
                    ->label(__('filament.resources.gallery.fields.sort_order'))
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label(__('filament.resources.gallery.fields.is_published'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('scope')
                    ->label(__('filament.resources.gallery.fields.scope'))
                    ->options([
                        Album::SCOPE_SITE => __('filament.resources.gallery.scopes.site'),
                        Album::SCOPE_PLAYER => __('filament.resources.gallery.scopes.player'),
                    ]),
                TernaryFilter::make('is_published')
                    ->label(__('filament.resources.gallery.fields.is_published')),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(__('filament.resources.gallery.actions.publish'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Album $record): bool => ! $record->is_published
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (Album $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        $record->update(['is_published' => true]);
                    }),
                Action::make('unpublish')
                    ->label(__('filament.resources.gallery.actions.unpublish'))
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Album $record): bool => $record->is_published
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (Album $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        $record->update(['is_published' => false]);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            PhotosRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListAlbums::route('/'),
            'create' => CreateAlbum::route('/create'),
            'edit' => EditAlbum::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('albums.manage_site') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('createSite', Album::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('photos');
    }
}
