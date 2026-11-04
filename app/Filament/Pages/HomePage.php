<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Exceptions\ImageProcessingException;
use App\Services\AuditLogger;
use App\Services\HomeImageUploader;
use App\Services\HomePageContent;
use App\Services\SettingsRepository;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Singleton editor for the public home page (`/`).
 *
 * Filament 5 has no singleton resources (only List/Create/Edit/View pages), so
 * the home page is a plain custom page: opening it goes straight to the form,
 * there is no list and no record. Values are stored in the `settings`
 * key/value store through {@see HomePageContent} / {@see SettingsRepository}.
 *
 * @property-read Schema $form
 */
class HomePage extends Page
{
    protected string $view = 'filament.pages.home-page';

    protected static ?string $slug = 'home';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    /**
     * Keeps the item above "News" (`NewsResource::$navigationSort = 1`) in the
     * "Content" navigation group.
     */
    protected static ?int $navigationSort = 0;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.home.label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.content');
    }

    public function getTitle(): string
    {
        return __('filament.home.title');
    }

    public function mount(HomePageContent $content): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($content->editable());
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('filament.home.sections.text'))
                    ->schema([
                        TextInput::make('meta_title')
                            ->label(__('filament.home.fields.meta_title'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('h1')
                            ->label(__('filament.home.fields.h1'))
                            ->required()
                            ->maxLength(255),
                        MarkdownEditor::make('body')
                            ->label(__('filament.home.fields.body'))
                            ->helperText(__('filament.home.fields.body_help'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make(__('filament.home.sections.hero_logo'))
                    ->schema([
                        FileUpload::make('hero_logo')
                            ->label(__('filament.home.fields.hero_logo'))
                            ->helperText(__('filament.home.fields.hero_logo_help'))
                            ->image()
                            // Mirrors HomeImageUploader::ALLOWED_MIMES.
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            ->disk('public')
                            // The file is re-encoded to WebP by HomeImageUploader
                            // in save(), so Filament must not store it itself.
                            ->storeFiles(false)
                            // Replacing the bundled asset is done with the toggle
                            // below, which also deletes the previous custom file.
                            ->deletable(false),
                        Toggle::make('reset_hero_logo')
                            ->label(__('filament.home.fields.reset_hero_logo'))
                            ->helperText(__('filament.home.fields.reset_hero_logo_help'))
                            ->visible(fn (Get $get): bool => filled($get('hero_logo'))),
                    ]),
                Section::make(__('filament.home.sections.main_image'))
                    ->schema([
                        FileUpload::make('main_image')
                            ->label(__('filament.home.fields.main_image'))
                            ->helperText(__('filament.home.fields.main_image_help'))
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            ->disk('public')
                            ->storeFiles(false)
                            ->deletable(false),
                        Toggle::make('reset_main_image')
                            ->label(__('filament.home.fields.reset_main_image'))
                            ->helperText(__('filament.home.fields.reset_main_image_help'))
                            ->visible(fn (Get $get): bool => filled($get('main_image'))),
                    ]),
            ]);
    }

    public function save(
        HomePageContent $content,
        HomeImageUploader $uploader,
        SettingsRepository $settings,
        AuditLogger $audit,
    ): void {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        try {
            $hero = $this->resolveImageSlot(
                slot: HomeImageUploader::SLOT_HERO,
                field: 'hero_logo',
                reset: (bool) ($data['reset_hero_logo'] ?? false),
                pathKey: HomePageContent::KEY_HERO_LOGO,
                data: $data,
                settings: $settings,
                uploader: $uploader,
            );

            $image = $this->resolveImageSlot(
                slot: HomeImageUploader::SLOT_IMAGE,
                field: 'main_image',
                reset: (bool) ($data['reset_main_image'] ?? false),
                pathKey: HomePageContent::KEY_MAIN_IMAGE,
                data: $data,
                settings: $settings,
                uploader: $uploader,
            );
        } catch (ImageProcessingException $e) {
            // Nothing is written when an upload is rejected: a partial save
            // would leave the page in a state the administrator did not see.
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            return;
        }

        $values = [
            HomePageContent::KEY_META_TITLE => trim((string) ($data['meta_title'] ?? '')),
            HomePageContent::KEY_H1 => trim((string) ($data['h1'] ?? '')),
            // An empty body is a valid choice: the text block is then hidden.
            HomePageContent::KEY_BODY => is_string($data['body'] ?? null) ? trim($data['body']) : '',
            HomePageContent::KEY_HERO_LOGO => $hero['path'],
            HomePageContent::KEY_MAIN_IMAGE => $image['path'],
        ];

        if ($image['reset']) {
            $values[HomePageContent::KEY_MAIN_IMAGE_WIDTH] = null;
            $values[HomePageContent::KEY_MAIN_IMAGE_HEIGHT] = null;
        } elseif ($image['width'] !== null && $image['height'] !== null) {
            // Real dimensions of the stored file, used for the intrinsic
            // width/height attributes on the front end.
            $values[HomePageContent::KEY_MAIN_IMAGE_WIDTH] = $image['width'];
            $values[HomePageContent::KEY_MAIN_IMAGE_HEIGHT] = $image['height'];
        }

        $previous = [];
        foreach (array_keys($values) as $key) {
            $previous[$key] = $settings->get($key);
        }

        $settings->setMany($values);

        $audit->log(
            action: 'home.updated',
            old: $previous,
            new: $values,
            recordIp: false,
        );

        Notification::make()
            ->success()
            ->title(__('filament.home.saved'))
            ->send();

        $this->mount($content);
    }

    /**
     * Resolve one image slot from the submitted form state.
     *
     * Three cases are supported: the reset toggle (delete the custom file and
     * fall back to the bundled asset), a new upload (re-encode to WebP, remove
     * the previous file) and "unchanged" (keep the stored path).
     *
     * @param  array<string, mixed>  $data
     * @return array{path: ?string, width: ?int, height: ?int, reset: bool}
     *
     * @throws ImageProcessingException
     */
    private function resolveImageSlot(
        string $slot,
        string $field,
        bool $reset,
        string $pathKey,
        array $data,
        SettingsRepository $settings,
        HomeImageUploader $uploader,
    ): array {
        $current = $settings->get($pathKey);
        $current = is_string($current) && trim($current) !== '' ? $current : null;

        if ($reset) {
            $uploader->remove($current);

            return ['path' => null, 'width' => null, 'height' => null, 'reset' => true];
        }

        $file = $this->uploadedFile($data[$field] ?? null);

        if ($file !== null) {
            $stored = $uploader->upload($file, $slot);
            $uploader->remove($current);

            return [
                'path' => $stored['path'],
                'width' => $stored['width'],
                'height' => $stored['height'],
                'reset' => false,
            ];
        }

        // Unchanged: only the path that was placed into the form may be kept.
        // Filament already rejects tampered paths; this is defence in depth.
        $submitted = $this->submittedPath($data[$field] ?? null);

        return [
            'path' => $submitted !== null && $submitted === $current ? $submitted : null,
            'width' => null,
            'height' => null,
            'reset' => false,
        ];
    }

    /**
     * Extract a freshly uploaded file from the field state.
     *
     * A single upload is normally a TemporaryUploadedFile, but the state can
     * also be an array keyed by upload id depending on the state cast.
     */
    private function uploadedFile(mixed $value): ?TemporaryUploadedFile
    {
        if ($value instanceof TemporaryUploadedFile) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $file) {
                if ($file instanceof TemporaryUploadedFile) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Extract an already stored path from the field state.
     */
    private function submittedPath(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value) !== '' ? $value : null;
        }

        if (is_array($value)) {
            foreach ($value as $file) {
                if (is_string($file) && trim($file) !== '') {
                    return $file;
                }
            }
        }

        return null;
    }
}
