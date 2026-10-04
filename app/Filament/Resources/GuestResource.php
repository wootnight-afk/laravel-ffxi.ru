<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuestResource\Pages\ListGuests;
use App\Models\GuestVisitor;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class GuestResource extends Resource
{
    protected static ?string $model = GuestVisitor::class;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('filament.resources.guests.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.guests.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.guests.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.community');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('filament.resources.guests.fields.display_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('hits')
                    ->label(__('filament.resources.guests.fields.hits'))
                    ->sortable(),
                TextColumn::make('first_seen_at')
                    ->label(__('filament.resources.guests.fields.first_seen_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label(__('filament.resources.guests.fields.last_seen_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('convertedUser.name')
                    ->label(__('filament.resources.guests.fields.converted_user'))
                    ->placeholder('—'),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
        ];
    }
}
