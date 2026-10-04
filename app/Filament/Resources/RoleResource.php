<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages\ListRoles;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('filament.resources.roles.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.roles.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.roles.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.system');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.resources.roles.fields.name'))
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label(__('filament.resources.roles.fields.guard')),
                TextColumn::make('permissions.name')
                    ->label(__('filament.resources.roles.fields.permissions'))
                    ->listWithLineBreaks()
                    ->limitList(12)
                    ->expandableLimitedList(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
        ];
    }
}
