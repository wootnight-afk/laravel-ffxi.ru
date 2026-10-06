<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RankResource\Pages\CreateRank;
use App\Filament\Resources\RankResource\Pages\EditRank;
use App\Filament\Resources\RankResource\Pages\ListRanks;
use App\Models\UserRank;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RankResource extends Resource
{
    protected static ?string $model = UserRank::class;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.ranks.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.system');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.ranks.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.ranks.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.resources.ranks.sections.main'))
                ->schema([
                    TextInput::make('key')
                        ->label(__('filament.resources.ranks.fields.key'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    TextInput::make('title')
                        ->label(__('filament.resources.ranks.fields.title'))
                        ->required()
                        ->maxLength(50),
                    TextInput::make('icon')
                        ->label(__('filament.resources.ranks.fields.icon'))
                        ->maxLength(16)
                        ->default('')
                        ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                    TextInput::make('rating')
                        ->label(__('filament.resources.ranks.fields.rating'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(65535)
                        ->default(0),
                    ColorPicker::make('color')
                        ->label(__('filament.resources.ranks.fields.color'))
                        ->nullable(),
                    TextInput::make('sort_order')
                        ->label(__('filament.resources.ranks.fields.sort_order'))
                        ->numeric()
                        ->default(0),
                    Toggle::make('is_active')
                        ->label(__('filament.resources.ranks.fields.is_active'))
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
                    ->label(__('filament.resources.ranks.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->label(__('filament.resources.ranks.fields.key'))
                    ->searchable(),
                TextColumn::make('icon')
                    ->label(__('filament.resources.ranks.fields.icon'))
                    ->placeholder('—'),
                TextColumn::make('rating')
                    ->label(__('filament.resources.ranks.fields.rating'))
                    ->sortable(),
                ColorColumn::make('color')
                    ->label(__('filament.resources.ranks.fields.color')),
                TextColumn::make('sort_order')
                    ->label(__('filament.resources.ranks.fields.sort_order'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament.resources.ranks.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament.resources.ranks.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRanks::route('/'),
            'create' => CreateRank::route('/create'),
            'edit' => EditRank::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('ranks.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('ranks.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('ranks.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('ranks.manage') ?? false;
    }
}
