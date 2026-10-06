<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DashboardWidgetResource\Pages\CreateDashboardWidget;
use App\Filament\Resources\DashboardWidgetResource\Pages\EditDashboardWidget;
use App\Filament\Resources\DashboardWidgetResource\Pages\ListDashboardWidgets;
use App\Models\DashboardWidget;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
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

class DashboardWidgetResource extends Resource
{
    protected static ?string $model = DashboardWidget::class;

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.widgets.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.system');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.widgets.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.widgets.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.resources.widgets.sections.main'))
                ->schema([
                    TextInput::make('key')
                        ->label(__('filament.resources.widgets.fields.key'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    TextInput::make('title')
                        ->label(__('filament.resources.widgets.fields.title'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('type')
                        ->label(__('filament.resources.widgets.fields.type'))
                        ->required()
                        ->maxLength(30),
                    TextInput::make('column_span')
                        ->label(__('filament.resources.widgets.fields.column_span'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(255)
                        ->default(1),
                    TextInput::make('sort_order')
                        ->label(__('filament.resources.widgets.fields.sort_order'))
                        ->numeric()
                        ->default(0),
                    Toggle::make('is_active')
                        ->label(__('filament.resources.widgets.fields.is_active'))
                        ->default(true),
                    KeyValue::make('settings')
                        ->label(__('filament.resources.widgets.fields.settings'))
                        ->keyLabel(__('filament.resources.widgets.fields.settings_key'))
                        ->valueLabel(__('filament.resources.widgets.fields.settings_value'))
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.resources.widgets.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->label(__('filament.resources.widgets.fields.key'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('filament.resources.widgets.fields.type'))
                    ->badge(),
                TextColumn::make('column_span')
                    ->label(__('filament.resources.widgets.fields.column_span'))
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('filament.resources.widgets.fields.sort_order'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament.resources.widgets.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament.resources.widgets.fields.is_active')),
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
            'index' => ListDashboardWidgets::route('/'),
            'create' => CreateDashboardWidget::route('/create'),
            'edit' => EditDashboardWidget::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('widgets.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('widgets.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('widgets.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('widgets.manage') ?? false;
    }
}
