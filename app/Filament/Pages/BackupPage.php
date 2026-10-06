<?php

namespace App\Filament\Pages;

use App\Models\RestoreRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestoreRequestService;
use App\Services\SettingsRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * Admin-only backup management (STAGE-9-CONTRACT section 8).
 *
 * Lists the backups written by {@see BackupService}, creates new ones,
 * exposes the manifest (view/download), deletes backups and edits the runtime
 * retention settings. Restore is intentionally absent: it is CLI-only
 * (ADR-004 R4) and the restore-request flow lands in E9.5.
 *
 * @property-read Table $table
 */
class BackupPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.backup-page';

    protected static ?string $slug = 'backups';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * Scope B snapshots contain `.env` and application secrets, so the create
     * form must warn before such a backup is requested (ADR-004 section 3.4).
     */
    public static function scopeRequiresWarning(string $mode, ?string $scope): bool
    {
        return $mode !== BackupManifest::TYPE_DB_ONLY
            && $scope === BackupManifest::SCOPE_WHOLE_SITE;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.backup.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.system');
    }

    public function getTitle(): string
    {
        return __('filament.backup.title');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->backupRecords())
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('filament.backup.fields.created_at'))
                    ->dateTime(),
                TextColumn::make('type')
                    ->label(__('filament.backup.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('filament.backup.modes.'.$state)),
                TextColumn::make('scope')
                    ->label(__('filament.backup.fields.scope'))
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? '—'
                        : __('filament.backup.scopes.'.$state)),
                TextColumn::make('size_bytes')
                    ->label(__('filament.backup.fields.size'))
                    ->formatStateUsing(fn (int $state): string => $this->humanSize($state)),
                TextColumn::make('is_complete')
                    ->label(__('filament.backup.fields.is_complete'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('filament.backup.complete')
                        : __('filament.backup.incomplete')),
                TextColumn::make('triggered_by')
                    ->label(__('filament.backup.fields.triggered_by'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $this->triggerLabel($state)),
                TextColumn::make('created_by')
                    ->label(__('filament.backup.fields.created_by'))
                    ->placeholder('—'),
            ])
            ->headerActions([
                $this->buildCreateBackupAction(),
                $this->buildRetentionAction(),
            ])
            ->recordActions([
                $this->buildViewManifestAction(),
                $this->buildDownloadManifestAction(),
                $this->buildRestoreRequestAction(),
                $this->buildDeleteBackupAction(),
            ])
            ->paginated(false);
    }

    private function buildCreateBackupAction(): Action
    {
        return Action::make('createBackup')
            ->label(__('filament.backup.actions.create'))
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalHeading(__('filament.backup.actions.create'))
            ->modalSubmitActionLabel(__('filament.backup.actions.create'))
            ->visible(fn (): bool => static::canAccess())
            ->schema([
                Radio::make('mode')
                    ->label(__('filament.backup.fields.mode'))
                    ->options([
                        BackupManifest::TYPE_FULL => __('filament.backup.modes.full'),
                        BackupManifest::TYPE_SITE_ONLY => __('filament.backup.modes.site_only'),
                        BackupManifest::TYPE_DB_ONLY => __('filament.backup.modes.db_only'),
                    ])
                    ->default(BackupManifest::TYPE_FULL)
                    ->required()
                    ->live(),
                Radio::make('scope')
                    ->label(__('filament.backup.fields.scope'))
                    ->options([
                        BackupManifest::SCOPE_STORAGE_APP => __('filament.backup.scopes.storage_app'),
                        BackupManifest::SCOPE_WHOLE_SITE => __('filament.backup.scopes.whole_site'),
                    ])
                    ->default(BackupManifest::SCOPE_STORAGE_APP)
                    ->required(fn (Get $get): bool => $get('mode') !== BackupManifest::TYPE_DB_ONLY)
                    ->visible(fn (Get $get): bool => $get('mode') !== BackupManifest::TYPE_DB_ONLY)
                    ->live(),
                Callout::make(__('filament.backup.scope_b_warning_title'))
                    ->description(__('filament.backup.scope_b_warning'))
                    ->warning()
                    ->visible(fn (Get $get): bool => static::scopeRequiresWarning(
                        (string) $get('mode'),
                        $get('scope') === null ? null : (string) $get('scope'),
                    )),
            ])
            ->action(function (array $data): void {
                abort_unless(static::canAccess(), 403);

                /** @var User $actor */
                $actor = auth()->user();
                $mode = (string) $data['mode'];
                $scope = $mode === BackupManifest::TYPE_DB_ONLY
                    ? null
                    : (string) ($data['scope'] ?? '');

                try {
                    $manifest = app(BackupService::class)->create(
                        $mode,
                        $scope,
                        "admin:{$actor->getKey()}",
                        $actor->getKey(),
                    );
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament.backup.create_failed'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                // Non-security event: no IP/UA is recorded (contract section 12).
                app(AuditLogger::class)->log(
                    action: 'backup.created',
                    new: [
                        'backup_id' => $manifest->backupId,
                        'type' => $manifest->type,
                        'scope' => $manifest->scope,
                        'size_bytes' => $manifest->sizeBytes,
                    ],
                    recordIp: false,
                );

                Notification::make()
                    ->success()
                    ->title(__('filament.backup.created'))
                    ->send();

                $this->resetTable();
            });
    }

    private function buildRetentionAction(): Action
    {
        return Action::make('retention')
            ->label(__('filament.backup.actions.retention'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->modalHeading(__('filament.backup.actions.retention'))
            ->modalSubmitActionLabel(__('filament.backup.actions.retention_save'))
            ->visible(fn (): bool => static::canAccess())
            ->fillForm(fn (): array => $this->retentionValues())
            ->schema([
                TextInput::make('backup_retention_daily')
                    ->label(__('filament.backup.fields.retention_daily'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(365)
                    ->required(),
                TextInput::make('backup_retention_weekly')
                    ->label(__('filament.backup.fields.retention_weekly'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(52)
                    ->required(),
                TextInput::make('backup_retention_monthly')
                    ->label(__('filament.backup.fields.retention_monthly'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(120)
                    ->required(),
                TextInput::make('min_free_space_pct')
                    ->label(__('filament.backup.fields.min_free_space_pct'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(50)
                    ->required(),
            ])
            ->action(function (array $data): void {
                abort_unless(static::canAccess(), 403);

                app(SettingsRepository::class)->setMany([
                    'backup_retention_daily' => (int) $data['backup_retention_daily'],
                    'backup_retention_weekly' => (int) $data['backup_retention_weekly'],
                    'backup_retention_monthly' => (int) $data['backup_retention_monthly'],
                    'min_free_space_pct' => (int) $data['min_free_space_pct'],
                ]);

                Notification::make()
                    ->success()
                    ->title(__('filament.backup.retention_saved'))
                    ->send();
            });
    }

    private function buildViewManifestAction(): Action
    {
        return Action::make('viewManifest')
            ->label(__('filament.backup.actions.manifest'))
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->modalHeading(__('filament.backup.actions.manifest'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament.backup.actions.close'))
            ->visible(fn (): bool => static::canAccess())
            ->modalContent(function (array $record): Htmlable {
                $json = json_encode(
                    $record['manifest'] ?? [],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );

                return new HtmlString(
                    '<pre class="whitespace-pre-wrap break-all text-xs">'
                    .e($json === false ? '' : $json)
                    .'</pre>',
                );
            });
    }

    private function buildDownloadManifestAction(): Action
    {
        return Action::make('downloadManifest')
            ->label(__('filament.backup.actions.download'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => static::canAccess())
            ->action(function (array $record) {
                abort_unless(static::canAccess(), 403);

                $backupId = (string) ($record['backup_id'] ?? '');
                $json = json_encode(
                    $record['manifest'] ?? [],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );

                return response()->streamDownload(
                    function () use ($json): void {
                        echo $json === false ? '' : $json;
                    },
                    $backupId.'.manifest.json',
                    ['Content-Type' => 'application/json'],
                );
            });
    }

    /**
     * Create a restore request (HTTP never performs the restore itself).
     *
     * Re-auth (current password) is enforced by the form rule; MFA is enforced
     * by the panel middleware. The action only records a pending
     * {@see RestoreRequest} and shows the CLI instruction
     * (ADR-004 R4/R5, contract section 6.2).
     */
    private function buildRestoreRequestAction(): Action
    {
        return Action::make('restoreRequest')
            ->label(__('filament.backup.actions.restore'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('filament.backup.actions.restore'))
            ->modalDescription(__('filament.backup.restore_confirm'))
            ->modalSubmitActionLabel(__('filament.backup.actions.restore'))
            ->visible(fn (): bool => static::canAccess())
            ->schema([
                TextInput::make('password')
                    ->label(__('filament.reauth.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule('current_password'),
            ])
            ->action(function (array $record): void {
                abort_unless(static::canAccess(), 403);

                /** @var User $actor */
                $actor = auth()->user();
                $backupId = (string) ($record['backup_id'] ?? '');
                $manifest = app(BackupService::class)->find($backupId);

                if ($manifest === null) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament.backup.restore_failed'))
                        ->send();

                    return;
                }

                $request = app(RestoreRequestService::class)->create($backupId, (int) $actor->getKey());

                // Security event: IP/UA are recorded (contract section 12).
                app(AuditLogger::class)->log(
                    action: 'restore.requested',
                    new: [
                        'request_id' => $request->id,
                        'backup_id' => $backupId,
                        'expires_at' => $request->expires_at->toIso8601String(),
                    ],
                );

                Notification::make()
                    ->warning()
                    ->title(__('filament.backup.restore_requested'))
                    ->body(__('filament.backup.restore_instruction', ['id' => $request->id]))
                    ->persistent()
                    ->send();
            });
    }

    private function buildDeleteBackupAction(): Action
    {
        return Action::make('deleteBackup')
            ->label(__('filament.backup.actions.delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('filament.backup.actions.delete'))
            ->modalDescription(__('filament.backup.actions.delete_confirm'))
            ->visible(fn (): bool => static::canAccess())
            ->action(function (array $record): void {
                abort_unless(static::canAccess(), 403);

                $backupId = (string) ($record['backup_id'] ?? '');
                $manifest = app(BackupService::class)->find($backupId);

                if ($manifest === null) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament.backup.delete_failed'))
                        ->send();

                    return;
                }

                app(BackupService::class)->delete($backupId);

                // Non-security event: no IP/UA is recorded (contract section 12).
                app(AuditLogger::class)->log(
                    action: 'backup.deleted',
                    new: [
                        'backup_id' => $manifest->backupId,
                        'type' => $manifest->type,
                        'scope' => $manifest->scope,
                    ],
                    recordIp: false,
                );

                Notification::make()
                    ->success()
                    ->title(__('filament.backup.deleted'))
                    ->send();

                $this->resetTable();
            });
    }

    /**
     * Backups as Filament array records, newest first (contract section 8).
     *
     * @return array<int, array<string, mixed>>
     */
    private function backupRecords(): array
    {
        $manifests = app(BackupService::class)->list();

        usort(
            $manifests,
            static fn (BackupManifest $a, BackupManifest $b): int => strcmp($b->createdAt, $a->createdAt),
        );

        $userIds = array_values(array_filter(
            array_map(static fn (BackupManifest $manifest): ?int => $manifest->createdByUserId, $manifests),
        ));

        $names = $userIds === []
            ? collect()
            : User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return array_map(static function (BackupManifest $manifest) use ($names): array {
            $createdBy = $manifest->createdByUserId !== null
                ? ($names[$manifest->createdByUserId] ?? '#'.$manifest->createdByUserId)
                : null;

            return [
                '__key' => $manifest->backupId,
                'backup_id' => $manifest->backupId,
                'created_at' => $manifest->createdAt,
                'type' => $manifest->type,
                'scope' => $manifest->scope,
                'is_complete' => $manifest->isComplete,
                'size_bytes' => $manifest->sizeBytes,
                'triggered_by' => $manifest->triggeredBy,
                'created_by' => $createdBy,
                'manifest' => $manifest->toArray(),
            ];
        }, $manifests);
    }

    /**
     * @return array<string, int>
     */
    private function retentionValues(): array
    {
        $settings = app(SettingsRepository::class);

        return [
            'backup_retention_daily' => $settings->int('backup_retention_daily', 7),
            'backup_retention_weekly' => $settings->int('backup_retention_weekly', 4),
            'backup_retention_monthly' => $settings->int('backup_retention_monthly', 12),
            'min_free_space_pct' => $settings->int('min_free_space_pct', 5),
        ];
    }

    private function triggerLabel(string $triggeredBy): string
    {
        if (str_starts_with($triggeredBy, 'admin:')) {
            return __('filament.backup.triggers.admin');
        }

        return __('filament.backup.triggers.'.$triggeredBy);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                return number_format($value, 2).' '.$unit;
            }

            $value /= 1024;
        }

        return $bytes.' B';
    }
}
