<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SocialLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'socialLinks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.resources.users.fields.social_links');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('filament.resources.users.fields.social_type')),
                TextColumn::make('username')
                    ->label(__('filament.resources.users.fields.username')),
                TextColumn::make('url')
                    ->label(__('filament.resources.users.fields.url'))
                    ->limit(60),
                IconColumn::make('is_visible')
                    ->label(__('filament.resources.users.fields.is_visible'))
                    ->boolean(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
