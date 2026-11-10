<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\LogsAdminActivity;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Services\AuditLogger;
use App\Services\PageEditorProfile;
use App\Services\PageMenuManager;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PageResource extends Resource
{
    use LogsAdminActivity;

    protected static ?string $model = Page::class;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.pages.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'content';
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.pages.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.pages.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')
                    ->label(__('filament.page_registry.kind'))
                    ->state(fn (array|Page $record): string => $record instanceof Page ? 'cms' : $record['kind'])
                    ->formatStateUsing(fn (string $state): string => __('filament.page_registry.kinds.'.$state)),
                TextColumn::make('title')
                    ->label(__('filament.resources.pages.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('url')
                    ->label('URL')
                    ->state(fn (array|Page $record): string => $record instanceof Page ? '/'.$record->slug : $record['url']),
                TextColumn::make('slug')
                    ->label(__('filament.resources.pages.fields.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_published')
                    ->label(__('filament.resources.pages.fields.is_published'))
                    ->boolean(),
                IconColumn::make('show_in_menu')
                    ->label(__('filament.resources.pages.fields.show_in_menu'))
                    ->boolean(),
                IconColumn::make('show_in_footer')
                    ->label(__('filament.resources.pages.fields.show_in_footer'))
                    ->boolean(),
                TextColumn::make('menu_order')
                    ->label(__('filament.resources.pages.fields.menu_order'))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('filament.resources.pages.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(null)
            ->defaultSort('menu_order')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('filament.resources.pages.fields.is_published')),
                TernaryFilter::make('show_in_menu')
                    ->label(__('filament.resources.pages.fields.show_in_menu')),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(__('filament.resources.pages.actions.publish'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (array|Page $record): bool => $record instanceof Page && ! $record->is_published
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (Page $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        $record->update(['is_published' => true]);

                        app(AuditLogger::class)->log(
                            action: 'page.published',
                            subject: $record,
                            old: ['is_published' => false],
                            new: ['is_published' => true],
                            recordIp: false,
                        );
                    }),
                Action::make('unpublish')
                    ->label(__('filament.resources.pages.actions.unpublish'))
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (array|Page $record): bool => $record instanceof Page && $record->is_published
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (Page $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        $record->update(['is_published' => false]);

                        app(AuditLogger::class)->log(
                            action: 'page.unpublished',
                            subject: $record,
                            old: ['is_published' => true],
                            new: ['is_published' => false],
                            recordIp: false,
                        );
                    }),
                Action::make('editor')
                    ->label(__('filament.page_registry.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (array|Page $record): bool => static::editorUrl($record) !== null)
                    ->url(fn (array|Page $record): ?string => static::editorUrl($record)),
                static::deleteAction()
                    ->hidden(fn (array|Page $record): bool => ! $record instanceof Page || ! static::canDelete($record)),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }

    /**
     * URL of the unified editor for a registry row.
     *
     * CMS pages use their model key; a system page uses its route name. Rows
     * without a profile (templates, auth pages, cabinet tabs) stay read-only.
     *
     * @param  array<string, mixed>|Page  $record
     */
    public static function editorUrl(array|Page $record): ?string
    {
        if ($record instanceof Page) {
            if (! static::canEdit($record)) {
                return null;
            }

            return EditPage::getUrl(['record' => 'cms-'.$record->getKey()]);
        }

        $route = (string) ($record['route'] ?? '');
        $profile = PageEditorProfile::forKey($route);

        if ($profile === null || ($profile['kind'] ?? null) !== PageEditorProfile::KIND_SYSTEM) {
            return null;
        }

        if (! (auth()->user()?->isAdmin() ?? false)) {
            return null;
        }

        return EditPage::getUrl(['record' => 'sys.'.$route]);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->databaseTransaction()
            ->using(function (Page $record): bool {
                abort_unless(static::canDelete($record), 403);

                return app(PageMenuManager::class)->delete($record);
            })
            // PageMenuManager records deletion inside the same transaction.
            ->after(fn (): null => null);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('pages.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('pages.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }
}
