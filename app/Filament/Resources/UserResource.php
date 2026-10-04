<?php

namespace App\Filament\Resources;

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\RelationManagers\SocialLinksRelationManager;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.users.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.community');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.users.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('roles')
                ->label(__('filament.resources.users.fields.roles'))
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->whereIn('name', ['admin', 'editor', 'user'])
                        ->where('guard_name', 'web'),
                )
                ->multiple()
                ->preload()
                ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
            Select::make('rank_id')
                ->label(__('filament.resources.users.fields.rank'))
                ->relationship(name: 'rank', titleAttribute: 'title')
                ->searchable()
                ->preload()
                ->nullable(),
            DateTimePicker::make('email_verified_at')
                ->label(__('filament.resources.users.fields.email_verified_at'))
                ->seconds(false)
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.resources.users.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('filament.resources.users.fields.email'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label(__('filament.resources.users.fields.roles'))
                    ->listWithLineBreaks(),
                TextColumn::make('rank.title')
                    ->label(__('filament.resources.users.fields.rank'))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('filament.resources.users.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label()),
                TextColumn::make('request_action')
                    ->label(__('filament.resources.users.fields.request_action'))
                    ->state(fn (User $record): ?string => match ($record->status) {
                        UserStatus::DeletionRequested => __('filament.user_status.deletion_requested'),
                        UserStatus::Suspended => __('filament.user_status.suspended'),
                        default => null,
                    })
                    ->placeholder('—'),
                TextColumn::make('request_reason')
                    ->label(__('filament.resources.users.fields.request_reason'))
                    ->state(fn (User $record): ?string => match ($record->status) {
                        UserStatus::DeletionRequested => $record->deletion_reason,
                        UserStatus::Suspended => $record->suspension_reason,
                        default => null,
                    })
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('request_date')
                    ->label(__('filament.resources.users.fields.request_date'))
                    ->state(fn (User $record) => match ($record->status) {
                        UserStatus::DeletionRequested => $record->deletion_requested_at,
                        UserStatus::Suspended => $record->suspended_at,
                        default => null,
                    })
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('email_verified_at')
                    ->label(__('filament.resources.users.fields.email_verified_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.resources.users.fields.status'))
                    ->options(collect(UserStatus::cases())
                        ->mapWithKeys(fn (UserStatus $status): array => [$status->value => $status->label()])
                        ->all()),
                Filter::make('account_requests')
                    ->label(__('filament.resources.users.fields.account_requests'))
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                        UserStatus::DeletionRequested->value,
                        UserStatus::Suspended->value,
                    ])),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('filament.resources.users.actions.edit')),
                Action::make('restore')
                    ->label(__('filament.resources.users.actions.restore'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('filament.resources.users.actions.restore_confirm'))
                    ->successNotificationTitle(__('filament.resources.users.actions.restored'))
                    ->visible(fn (User $record): bool => $record->status !== UserStatus::Active
                        && (auth()->user()?->can('users.manage') ?? false))
                    ->action(function (User $record): void {
                        abort_unless(auth()->user()?->can('users.manage'), 403);

                        $record->forceFill(['status' => UserStatus::Active])->save();
                    }),
            ])
            ->recordUrl(null);
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            SocialLinksRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
