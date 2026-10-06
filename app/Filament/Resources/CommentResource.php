<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\LogsAdminActivity;
use App\Filament\Resources\CommentResource\Pages\EditComment;
use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Models\Comment;
use App\Services\AdminActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;

class CommentResource extends Resource
{
    use LogsAdminActivity;

    protected static ?string $model = Comment::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.comments.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.comments.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.comments.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.resources.comments.sections.main'))
                ->schema([
                    Textarea::make('body')
                        ->label(__('filament.resources.comments.fields.body'))
                        ->required()
                        ->maxLength(2000)
                        ->rows(5)
                        ->columnSpanFull(),
                    Select::make('status')
                        ->label(__('filament.resources.comments.fields.status'))
                        ->options(self::statusOptions())
                        ->required(),
                    Toggle::make('is_reported')
                        ->label(__('filament.resources.comments.fields.is_reported')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('body')
                    ->label(__('filament.resources.comments.fields.body'))
                    ->searchable()
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label(__('filament.resources.comments.fields.author'))
                    ->placeholder('—'),
                TextColumn::make('target')
                    ->label(__('filament.resources.comments.fields.target'))
                    ->state(fn (Comment $record): string => self::targetLabel($record)),
                TextColumn::make('status')
                    ->label(__('filament.resources.comments.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("filament.resources.comments.statuses.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        Comment::STATUS_APPROVED => 'success',
                        Comment::STATUS_PENDING => 'warning',
                        Comment::STATUS_REJECTED => 'danger',
                        Comment::STATUS_SPAM => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('is_reported')
                    ->label(__('filament.resources.comments.fields.is_reported'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('filament.resources.comments.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.resources.comments.fields.status'))
                    ->options(self::statusOptions()),
                TernaryFilter::make('is_reported')
                    ->label(__('filament.resources.comments.fields.is_reported')),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('filament.resources.comments.actions.approve'))
                    ->color('success')
                    ->visible(fn (Comment $record): bool => $record->status !== Comment::STATUS_APPROVED
                        && (auth()->user()?->can('comments.moderate') ?? false))
                    ->action(function (Comment $record): void {
                        abort_unless(auth()->user()?->can('comments.moderate'), 403);

                        $record->update(['status' => Comment::STATUS_APPROVED, 'is_reported' => false]);

                        app(AdminActivityLogger::class)->record($record, 'approved');
                    }),
                Action::make('reject')
                    ->label(__('filament.resources.comments.actions.reject'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Comment $record): bool => $record->status !== Comment::STATUS_REJECTED
                        && (auth()->user()?->can('comments.moderate') ?? false))
                    ->action(function (Comment $record): void {
                        abort_unless(auth()->user()?->can('comments.moderate'), 403);

                        $record->update(['status' => Comment::STATUS_REJECTED]);

                        app(AdminActivityLogger::class)->record($record, 'rejected');
                    }),
                Action::make('spam')
                    ->label(__('filament.resources.comments.actions.spam'))
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Comment $record): bool => $record->status !== Comment::STATUS_SPAM
                        && (auth()->user()?->can('comments.moderate') ?? false))
                    ->action(function (Comment $record): void {
                        abort_unless(auth()->user()?->can('comments.moderate'), 403);

                        $record->update(['status' => Comment::STATUS_SPAM]);

                        app(AdminActivityLogger::class)->record($record, 'spam');
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label(__('filament.resources.comments.actions.approve'))
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->each(function (Comment $record): void {
                                abort_unless(auth()->user()?->can('comments.moderate'), 403);

                                $record->update(['status' => Comment::STATUS_APPROVED, 'is_reported' => false]);

                                app(AdminActivityLogger::class)->record($record, 'approved');
                            });
                        }),
                    BulkAction::make('reject')
                        ->label(__('filament.resources.comments.actions.reject'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (Comment $record): void {
                                abort_unless(auth()->user()?->can('comments.moderate'), 403);

                                $record->update(['status' => Comment::STATUS_REJECTED]);

                                app(AdminActivityLogger::class)->record($record, 'rejected');
                            });
                        }),
                    BulkAction::make('spam')
                        ->label(__('filament.resources.comments.actions.spam'))
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (Comment $record): void {
                                abort_unless(auth()->user()?->can('comments.moderate'), 403);

                                $record->update(['status' => Comment::STATUS_SPAM]);

                                app(AdminActivityLogger::class)->record($record, 'spam');
                            });
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListComments::route('/'),
            'edit' => EditComment::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect([
            Comment::STATUS_APPROVED,
            Comment::STATUS_PENDING,
            Comment::STATUS_REJECTED,
            Comment::STATUS_SPAM,
        ])->mapWithKeys(fn (string $status): array => [
            $status => __("filament.resources.comments.statuses.{$status}"),
        ])->all();
    }

    private static function targetLabel(Comment $record): string
    {
        $type = match (class_basename($record->commentable_type)) {
            'News' => __('filament.resources.comments.targets.news'),
            'Photo' => __('filament.resources.comments.targets.photo'),
            'Event' => __('filament.resources.comments.targets.event'),
            default => class_basename($record->commentable_type),
        };

        return "{$type} #{$record->commentable_id}";
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('comments.moderate') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('comments.moderate') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('comments.moderate') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }
}
