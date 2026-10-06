<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\LogsAdminActivity;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
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

class PageResource extends Resource
{
    use LogsAdminActivity;

    protected static ?string $model = Page::class;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.pages.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.pages.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.pages.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.resources.pages.sections.main'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('filament.resources.pages.fields.title'))
                        ->required()
                        ->maxLength(150),
                    TextInput::make('slug')
                        ->label(__('filament.resources.pages.fields.slug'))
                        ->maxLength(120)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('filament.resources.pages.fields.slug_help')),
                    MarkdownEditor::make('body')
                        ->label(__('filament.resources.pages.fields.body'))
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('filament.resources.pages.sections.menu'))
                ->schema([
                    Toggle::make('is_published')
                        ->label(__('filament.resources.pages.fields.is_published')),
                    Toggle::make('show_in_menu')
                        ->label(__('filament.resources.pages.fields.show_in_menu')),
                    TextInput::make('menu_order')
                        ->label(__('filament.resources.pages.fields.menu_order'))
                        ->numeric()
                        ->default(0),
                ])
                ->columns(3),
            Section::make(__('filament.resources.pages.sections.meta'))
                ->schema([
                    TextInput::make('meta_title')
                        ->label(__('filament.resources.pages.fields.meta_title'))
                        ->maxLength(180),
                    TextInput::make('meta_description')
                        ->label(__('filament.resources.pages.fields.meta_description'))
                        ->maxLength(255),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.resources.pages.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(60),
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
                TextColumn::make('menu_order')
                    ->label(__('filament.resources.pages.fields.menu_order'))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('filament.resources.pages.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
                    ->visible(fn (Page $record): bool => ! $record->is_published
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (Page $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        $record->update(['is_published' => true]);
                    }),
                Action::make('unpublish')
                    ->label(__('filament.resources.pages.actions.unpublish'))
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Page $record): bool => $record->is_published
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (Page $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        $record->update(['is_published' => false]);
                    }),
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
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
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
