<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventTypeResource\Pages\CreateEventType;
use App\Filament\Resources\EventTypeResource\Pages\EditEventType;
use App\Filament\Resources\EventTypeResource\Pages\ListEventTypes;
use App\Models\EventType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EventTypeResource extends Resource
{
    protected static ?string $model = EventType::class;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.event_types.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.system');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.event_types.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.event_types.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.resources.event_types.sections.main'))
                ->schema([
                    TextInput::make('key')
                        ->label(__('filament.resources.event_types.fields.key'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    TextInput::make('title')
                        ->label(__('filament.resources.event_types.fields.title'))
                        ->required()
                        ->maxLength(50),
                    TextInput::make('icon')
                        ->label(__('filament.resources.event_types.fields.icon'))
                        ->maxLength(16),
                    TextInput::make('sort_order')
                        ->label(__('filament.resources.event_types.fields.sort_order'))
                        ->numeric()
                        ->default(0),
                    Toggle::make('is_active')
                        ->label(__('filament.resources.event_types.fields.is_active'))
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
                    ->label(__('filament.resources.event_types.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->label(__('filament.resources.event_types.fields.key'))
                    ->searchable(),
                TextColumn::make('icon')
                    ->label(__('filament.resources.event_types.fields.icon'))
                    ->placeholder('—'),
                TextColumn::make('sort_order')
                    ->label(__('filament.resources.event_types.fields.sort_order'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament.resources.event_types.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament.resources.event_types.fields.is_active')),
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
            'index' => ListEventTypes::route('/'),
            'create' => CreateEventType::route('/create'),
            'edit' => EditEventType::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
