<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Exceptions\ImageProcessingException;
use App\Services\AuditLogger;
use App\Services\HomeImageUploader;
use App\Services\NewsPageContent;
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

/**
 * Editor for the static header of the public news page (`/news`).
 *
 * The news list itself (query, loop, pagination) is not configurable here: this
 * page only manages the title, H1, header image and introductory text above the
 * divider. Values are stored in the `settings` key/value store through
 * {@see NewsPageContent} / {@see SettingsRepository}, so there is no record and
 * no list — the page opens the form directly.
 *
 * The page is intentionally absent from the sidebar: the entry point is the
 * "Page editor" action in the header of the news list.
 *
 * @property-read Schema $form
 */
class NewsPage extends Page
{
    protected string $view = 'filament.pages.news-page';

    protected static ?string $slug = 'news-page';

    protected static ?string $title = null;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return __('filament.news_page.title');
    }

    public function getHeading(): string
    {
        return __('filament.news_page.title');
    }

    public function mount(NewsPageContent $content): void
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
                Section::make(__('filament.news_page.sections.text'))
                    ->schema([
                        TextInput::make('meta_title')
                            ->label(__('filament.news_page.fields.meta_title'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('h1')
                            ->label(__('filament.news_page.fields.h1'))
                            ->required()
                            ->maxLength(255),
                        MarkdownEditor::make('body')
                            ->label(__('filament.news_page.fields.body'))
                            ->helperText(__('filament.news_page.fields.body_help'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make(__('filament.news_page.sections.main_image'))
                    ->schema([
                        FileUpload::make('main_image')
                            ->label(__('filament.news_page.fields.main_image'))
                            ->helperText(__('filament.news_page.fields.main_image_help'))
                            ->image()
                            // Mirrors HomeImageUploader::ALLOWED_MIMES.
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            ->disk('public')
                            // The file is re-encoded to WebP by HomeImageUploader
                            // in save(), so Filament must not store it itself.
                            ->storeFiles(false)
                            ->deletable(false),
                        Toggle::make('reset_main_image')
                            ->label(__('filament.news_page.fields.reset_main_image'))
                            ->helperText(__('filament.news_page.fields.reset_main_image_help'))
                            ->visible(fn (Get $get): bool => filled($get('main_image'))),
                    ]),
            ]);
    }

    public function save(
        NewsPageContent $content,
        HomeImageUploader $uploader,
        SettingsRepository $settings,
        AuditLogger $audit,
    ): void {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        $current = $settings->get(NewsPageContent::KEY_MAIN_IMAGE);
        $current = is_string($current) && trim($current) !== '' ? $current : null;

        $image = ['path' => $current, 'width' => null, 'height' => null, 'reset' => false];

        try {
            if ((bool) ($data['reset_main_image'] ?? false)) {
                $uploader->remove($current);
                $image = ['path' => null, 'width' => null, 'height' => null, 'reset' => true];
            } else {
                $file = $this->uploadedFile($data['main_image'] ?? null);

                if ($file !== null) {
                    $stored = $uploader->upload($file, HomeImageUploader::SLOT_NEWS_PAGE);
                    $uploader->remove($current);
                    $image = [
                        'path' => $stored['path'],
                        'width' => $stored['width'],
                        'height' => $stored['height'],
                        'reset' => false,
                    ];
                } else {
                    // Unchanged: only the path that was placed into the form may
                    // be kept. Filament already rejects tampered paths; this is
                    // defence in depth.
                    $submitted = $this->submittedPath($data['main_image'] ?? null);
                    $image['path'] = $submitted !== null && $submitted === $current ? $submitted : null;
                }
            }
        } catch (ImageProcessingException $e) {
            // Nothing is written when the upload is rejected: a partial save
            // would leave the page in a state the administrator did not see.
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            return;
        }

        $values = [
            NewsPageContent::KEY_META_TITLE => trim((string) ($data['meta_title'] ?? '')),
            NewsPageContent::KEY_H1 => trim((string) ($data['h1'] ?? '')),
            // An empty body is a valid choice: the text block is then hidden.
            NewsPageContent::KEY_BODY => is_string($data['body'] ?? null) ? trim($data['body']) : '',
            NewsPageContent::KEY_MAIN_IMAGE => $image['path'],
        ];

        if ($image['reset']) {
            $values[NewsPageContent::KEY_MAIN_IMAGE_WIDTH] = null;
            $values[NewsPageContent::KEY_MAIN_IMAGE_HEIGHT] = null;
        } elseif ($image['width'] !== null && $image['height'] !== null) {
            // Real dimensions of the stored file, used for the intrinsic
            // width/height attributes on the front end.
            $values[NewsPageContent::KEY_MAIN_IMAGE_WIDTH] = $image['width'];
            $values[NewsPageContent::KEY_MAIN_IMAGE_HEIGHT] = $image['height'];
        }

        $previous = [];
        foreach (array_keys($values) as $key) {
            $previous[$key] = $settings->get($key);
        }

        $settings->setMany($values);

        $audit->log(
            action: 'news_page.updated',
            old: $previous,
            new: $values,
            recordIp: false,
        );

        Notification::make()
            ->success()
            ->title(__('filament.news_page.saved'))
            ->send();

        $this->mount($content);
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
