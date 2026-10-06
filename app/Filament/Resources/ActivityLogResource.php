<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Models\AdminAuditLog;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = AdminAuditLog::class;

    protected static ?int $navigationSort = 8;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.audit_logs.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.system');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.audit_logs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.audit_logs.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('filament.resources.audit_logs.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('filament.resources.audit_logs.fields.actor'))
                    ->placeholder('—'),
                TextColumn::make('action')
                    ->label(__('filament.resources.audit_logs.fields.action'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('subject')
                    ->label(__('filament.resources.audit_logs.fields.subject'))
                    ->state(fn (AdminAuditLog $record): string => $record->subject_type
                        ? class_basename($record->subject_type).' #'.$record->subject_id
                        : '—'),
                TextColumn::make('ip')
                    ->label(__('filament.resources.audit_logs.fields.ip'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label(__('filament.resources.audit_logs.fields.action'))
                    ->options(fn (): array => AdminAuditLog::query()
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all())
                    ->searchable(),
                SelectFilter::make('user_id')
                    ->label(__('filament.resources.audit_logs.fields.actor'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->label(__('filament.resources.audit_logs.fields.created_at'))
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('filament.resources.audit_logs.fields.date_from')),
                        DatePicker::make('until')
                            ->label(__('filament.resources.audit_logs.fields.date_until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),
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
            'index' => ListActivityLogs::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('admin') && $user->can('audit.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
