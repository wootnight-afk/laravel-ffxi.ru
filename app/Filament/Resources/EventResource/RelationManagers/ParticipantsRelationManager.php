<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Enums\EventParticipantStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.resources.events.sections.participants');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('events.manage_any') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('filament.resources.events.fields.participant'))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('filament.resources.events.fields.participant_status'))
                    ->badge()
                    ->formatStateUsing(fn (EventParticipantStatus $state): string => __("filament.resources.events.participant_statuses.{$state->value}"))
                    ->color(fn (EventParticipantStatus $state): string => match ($state) {
                        EventParticipantStatus::Joined => 'success',
                        EventParticipantStatus::Left => 'gray',
                    }),
                TextColumn::make('joined_at')
                    ->label(__('filament.resources.events.fields.joined_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('left_at')
                    ->label(__('filament.resources.events.fields.left_at'))
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
