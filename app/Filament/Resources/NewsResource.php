<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\LogsAdminActivity;
use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Filament\Resources\NewsResource\Pages\EditNews;
use App\Filament\Resources\NewsResource\Pages\ListNews;
use App\Models\News;
use App\Services\AdminActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;

class NewsResource extends Resource
{
    use LogsAdminActivity;

    protected static ?string $model = News::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.news.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.news.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.news.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.resources.news.sections.main'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('filament.resources.news.fields.title'))
                        ->required()
                        ->maxLength(150),
                    TextInput::make('slug')
                        ->label(__('filament.resources.news.fields.slug'))
                        ->maxLength(150)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('filament.resources.news.fields.slug_help')),
                    Textarea::make('excerpt')
                        ->label(__('filament.resources.news.fields.excerpt'))
                        ->maxLength(300)
                        ->rows(2)
                        ->columnSpanFull(),
                    MarkdownEditor::make('body')
                        ->label(__('filament.resources.news.fields.body'))
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('filament.resources.news.sections.meta'))
                ->schema([
                    Select::make('scope')
                        ->label(__('filament.resources.news.fields.scope'))
                        ->options([
                            News::SCOPE_SITE => __('filament.resources.news.scopes.site'),
                            News::SCOPE_PLAYER => __('filament.resources.news.scopes.player'),
                        ])
                        ->required()
                        ->default(News::SCOPE_SITE),
                    Select::make('user_id')
                        ->label(__('filament.resources.news.fields.author'))
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                        ->required(fn (): bool => auth()->user()?->isAdmin() ?? false),
                    Select::make('status')
                        ->label(__('filament.resources.news.fields.status'))
                        ->options(self::statusOptions())
                        ->required()
                        ->default(News::STATUS_DRAFT),
                    DateTimePicker::make('published_at')
                        ->label(__('filament.resources.news.fields.published_at'))
                        ->seconds(false)
                        ->nullable(),
                    Textarea::make('rejection_reason')
                        ->label(__('filament.resources.news.fields.rejection_reason'))
                        ->maxLength(300)
                        ->rows(2)
                        ->visible(fn (callable $get): bool => $get('status') === News::STATUS_REJECTED)
                        ->required(fn (callable $get): bool => $get('status') === News::STATUS_REJECTED),
                    Toggle::make('is_pinned')
                        ->label(__('filament.resources.news.fields.is_pinned')),
                    Toggle::make('comments_enabled')
                        ->label(__('filament.resources.news.fields.comments_enabled'))
                        ->default(true),
                ])
                ->columns(2),
            Section::make(__('filament.resources.news.sections.cover'))
                ->schema([
                    FileUpload::make('cover')
                        ->label(__('filament.resources.news.fields.cover'))
                        ->image()
                        ->maxSize(4096)
                        ->storeFiles(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.resources.news.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('user.name')
                    ->label(__('filament.resources.news.fields.author'))
                    ->placeholder('—'),
                TextColumn::make('scope')
                    ->label(__('filament.resources.news.fields.scope'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("filament.resources.news.scopes.{$state}")),
                TextColumn::make('status')
                    ->label(__('filament.resources.news.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("filament.resources.news.statuses.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        News::STATUS_PUBLISHED => 'success',
                        News::STATUS_PENDING => 'warning',
                        News::STATUS_REJECTED => 'danger',
                        News::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')
                    ->label(__('filament.resources.news.fields.published_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('views')
                    ->label(__('filament.resources.news.fields.views'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('comments_count')
                    ->label(__('filament.resources.news.fields.comments'))
                    ->counts('comments')
                    ->toggleable(),
                IconColumn::make('is_pinned')
                    ->label(__('filament.resources.news.fields.is_pinned'))
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('scope')
                    ->label(__('filament.resources.news.fields.scope'))
                    ->options([
                        News::SCOPE_SITE => __('filament.resources.news.scopes.site'),
                        News::SCOPE_PLAYER => __('filament.resources.news.scopes.player'),
                    ]),
                SelectFilter::make('status')
                    ->label(__('filament.resources.news.fields.status'))
                    ->options(self::statusOptions()),
                SelectFilter::make('user_id')
                    ->label(__('filament.resources.news.fields.author'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('filament.resources.news.actions.preview'))
                    ->modalHeading(fn (News $record): string => $record->title)
                    ->modalContent(fn (News $record) => view('filament.news.preview', ['news' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament.resources.news.actions.close')),
                Action::make('publish')
                    ->label(__('filament.resources.news.actions.publish'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (News $record): bool => $record->status !== News::STATUS_PUBLISHED
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (News $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        $record->update([
                            'status' => News::STATUS_PUBLISHED,
                            'published_at' => $record->published_at ?? now(),
                            'rejection_reason' => null,
                        ]);

                        app(AdminActivityLogger::class)->record($record, 'published');
                    }),
                Action::make('reject')
                    ->label(__('filament.resources.news.actions.reject'))
                    ->color('danger')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label(__('filament.resources.news.fields.rejection_reason'))
                            ->required()
                            ->maxLength(300)
                            ->rows(3),
                    ])
                    ->visible(fn (News $record): bool => $record->status !== News::STATUS_REJECTED
                        && (auth()->user()?->can('moderate', $record) ?? false))
                    ->action(function (News $record, array $data): void {
                        abort_unless(auth()->user()?->can('moderate', $record), 403);

                        $record->update([
                            'status' => News::STATUS_REJECTED,
                            'rejection_reason' => $data['rejection_reason'],
                            'published_at' => null,
                        ]);

                        app(AdminActivityLogger::class)->record($record, 'rejected');
                    }),
                Action::make('archive')
                    ->label(__('filament.resources.news.actions.archive'))
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (News $record): bool => $record->status !== News::STATUS_ARCHIVED
                        && (auth()->user()?->can('archive', $record) ?? false))
                    ->action(function (News $record): void {
                        abort_unless(auth()->user()?->can('archive', $record), 403);

                        $record->update(['status' => News::STATUS_ARCHIVED]);

                        app(AdminActivityLogger::class)->record($record, 'archived');
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label(__('filament.resources.news.actions.publish'))
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (News $record): void {
                                abort_unless(auth()->user()?->can('update', $record), 403);

                                $record->update([
                                    'status' => News::STATUS_PUBLISHED,
                                    'published_at' => $record->published_at ?? now(),
                                    'rejection_reason' => null,
                                ]);

                                app(AdminActivityLogger::class)->record($record, 'published');
                            });
                        }),
                    BulkAction::make('archive')
                        ->label(__('filament.resources.news.actions.archive'))
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (News $record): void {
                                abort_unless(auth()->user()?->can('archive', $record), 403);

                                $record->update(['status' => News::STATUS_ARCHIVED]);

                                app(AdminActivityLogger::class)->record($record, 'archived');
                            });
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListNews::route('/'),
            'create' => CreateNews::route('/create'),
            'edit' => EditNews::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect([
            News::STATUS_DRAFT,
            News::STATUS_PENDING,
            News::STATUS_PUBLISHED,
            News::STATUS_REJECTED,
            News::STATUS_ARCHIVED,
        ])->mapWithKeys(fn (string $status): array => [
            $status => __("filament.resources.news.statuses.{$status}"),
        ])->all();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('news.manage_site') || $user->can('news.moderate'));
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('news.manage_site') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }
}
