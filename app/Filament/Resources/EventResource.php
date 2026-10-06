<?php

namespace App\Filament\Resources;

use App\Enums\EventStatus;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\EventResource\RelationManagers\ParticipantsRelationManager;
use App\Models\Event;
use App\Services\EventService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.events.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.events.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.events.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.resources.events.sections.main'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('filament.resources.events.fields.title'))
                        ->required()
                        ->maxLength(150),
                    Select::make('type_id')
                        ->label(__('filament.resources.events.fields.type'))
                        ->relationship('type', 'title')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    MarkdownEditor::make('description')
                        ->label(__('filament.resources.events.fields.description'))
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('location')
                        ->label(__('filament.resources.events.fields.location'))
                        ->maxLength(120),
                ])
                ->columns(2),
            Section::make(__('filament.resources.events.sections.schedule'))
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->label(__('filament.resources.events.fields.starts_at'))
                        ->required()
                        ->seconds(false),
                    TextInput::make('duration_minutes')
                        ->label(__('filament.resources.events.fields.duration_minutes'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(60000)
                        ->nullable(),
                    TextInput::make('max_participants')
                        ->label(__('filament.resources.events.fields.max_participants'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->nullable(),
                    DateTimePicker::make('registration_close')
                        ->label(__('filament.resources.events.fields.registration_close'))
                        ->seconds(false)
                        ->nullable(),
                    Select::make('status')
                        ->label(__('filament.resources.events.fields.status'))
                        ->options(self::statusOptions())
                        ->default(EventStatus::Planned->value)
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.resources.events.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('type.title')
                    ->label(__('filament.resources.events.fields.type'))
                    ->placeholder('—'),
                TextColumn::make('starts_at')
                    ->label(__('filament.resources.events.fields.starts_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('filament.resources.events.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (EventStatus $state): string => __("filament.resources.events.statuses.{$state->value}"))
                    ->color(fn (EventStatus $state): string => match ($state) {
                        EventStatus::Planned => 'success',
                        EventStatus::Completed => 'gray',
                        EventStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('participants_count')
                    ->label(__('filament.resources.events.sections.participants'))
                    ->counts('participants'),
                TextColumn::make('max_participants')
                    ->label(__('filament.resources.events.fields.max_participants'))
                    ->placeholder('—'),
                TextColumn::make('location')
                    ->label(__('filament.resources.events.fields.location'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.resources.events.fields.status'))
                    ->options(self::statusOptions()),
                SelectFilter::make('type_id')
                    ->label(__('filament.resources.events.fields.type'))
                    ->relationship('type', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label(__('filament.resources.events.actions.cancel'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('filament.resources.events.actions.cancel_reason'))
                            ->maxLength(300)
                            ->rows(3),
                    ])
                    ->visible(fn (Event $record): bool => $record->status === EventStatus::Planned
                        && (auth()->user()?->can('cancel', $record) ?? false))
                    ->action(function (Event $record, array $data): void {
                        abort_unless(auth()->user()?->can('cancel', $record), 403);

                        app(EventService::class)->cancel(auth()->user(), $record, $data['reason'] ?? null);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            ParticipantsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(EventStatus::cases())
            ->mapWithKeys(fn (EventStatus $status): array => [
                $status->value => __("filament.resources.events.statuses.{$status->value}"),
            ])
            ->all();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('events.manage_any') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('events.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('events.manage_any') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['type'])->withCount('participants');
    }
}
