<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageResource\Pages;

use App\Exceptions\ImageProcessingException;
use App\Exceptions\SlugGenerationException;
use App\Filament\Resources\PageResource;
use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Services\AuditLogger;
use App\Services\HomeImageUploader;
use App\Services\PageContentProvider;
use App\Services\PageEditorProfile;
use App\Services\PageMenuManager;
use App\Services\SettingsRepository;
use App\Services\SiteNavigation;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page as FilamentPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;

/**
 * The single editor of every public page.
 *
 * One implementation serves both entry points:
 *
 *  - {@see CreatePage} (`/admin/pages/create`) creates CMS pages;
 *  - {@see EditPage} (`/admin/pages/{record}/edit`) edits CMS pages and the
 *    system pages of the registry.
 *
 * The form is built once from the union of all {@see PageEditorProfile}
 * sections. Sections and fields that do not apply to the current page type are
 * rendered disabled (and therefore visibly dimmed) and are marked
 * `dehydrated(false)`, so they never reach the saved data. Saving branches on
 * the page type: CMS pages go through {@see PageMenuManager} (record + menu
 * bindings + audit in one transaction), system pages go through their settings
 * provider plus the shared image uploader.
 *
 * @property-read Schema $form
 */
abstract class SitePageEditor extends FilamentPage
{
    protected static string $resource = PageResource::class;

    protected string $view = 'filament.pages.site-page-editor';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    protected ?Page $pageRecord = null;

    protected string $profileKey = 'cms';

    /**
     * The record key from the route.
     *
     * Livewire persists public properties only, so the target of the editor is
     * carried across requests by this locked string and re-resolved in
     * {@see hydrate()}. Without it the bound CMS record and the page type would
     * be lost after the first request of the component.
     */
    #[Locked]
    public ?string $recordKey = null;

    abstract protected function isCreateMode(): bool;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('pages.manage') ?? false;
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    /**
     * The bound CMS record, if any.
     *
     * Filament uses this to scope the `unique` rule of the slug field to the
     * edited record and to resolve relationship state.
     */
    public function getRecord(): ?Model
    {
        return $this->pageRecord;
    }

    public function mount(?string $record = null): void
    {
        abort_unless(static::canAccess(), 403);

        $this->recordKey = $record;

        if ($this->isCreateMode()) {
            $this->profileKey = 'cms';
            $this->data = $this->blankCmsData();

            $this->form->fill($this->data);

            return;
        }

        [$this->profileKey, $this->pageRecord] = $this->resolveTarget((string) $record);

        $this->authorizeProfile();

        $this->data = $this->initialData();
        $this->form->fill($this->data);
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! $this->isCreateMode() && $this->recordKey !== null) {
            [$this->profileKey, $this->pageRecord] = $this->resolveTarget($this->recordKey);
        }

        $this->authorizeProfile();
    }

    public function getTitle(): string|Htmlable
    {
        if ($this->isCreateMode()) {
            return __('filament.page_editor.create_title');
        }

        if ($this->pageRecord !== null) {
            return __('filament.page_editor.edit_title', ['title' => $this->pageRecord->title]);
        }

        return __('filament.page_editor.edit_title', ['title' => $this->profile()['title']]);
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    /**
     * Resolve the profile key and, for CMS pages, the bound record.
     *
     * @return array{0: string, 1: ?Page}
     */
    private function resolveTarget(string $record): array
    {
        if (str_starts_with($record, 'sys.')) {
            $key = substr($record, 4);

            if (PageEditorProfile::forKey($key) === null) {
                abort(404);
            }

            return [$key, null];
        }

        $id = str_starts_with($record, 'cms-') ? substr($record, 4) : $record;

        if (! ctype_digit($id)) {
            abort(404);
        }

        $page = Page::query()->find((int) $id);

        abort_if($page === null, 404);

        return ['cms', $page];
    }

    /**
     * System page content is administrator-only, matching the previous
     * dedicated editors. CMS pages stay available to editors.
     */
    private function authorizeProfile(): void
    {
        $profile = $this->profile();

        if (($profile['kind'] ?? null) === PageEditorProfile::KIND_SYSTEM) {
            abort_unless(auth()->user()?->isAdmin() ?? false, 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function profile(): array
    {
        $key = $this->isCreateMode() ? $this->currentType() : $this->profileKey;

        return PageEditorProfile::forKey($key) ?? PageEditorProfile::forKey('cms');
    }

    /**
     * Page type currently selected in the form (create) or fixed by the record
     * being edited.
     */
    protected function currentType(): string
    {
        return (string) ($this->data['page_type'] ?? 'cms');
    }

    private function sectionEditable(string $section): bool
    {
        return PageEditorProfile::sectionEditable($this->profile(), $section);
    }

    private function sectionReason(string $section): string
    {
        return PageEditorProfile::sectionReason($this->profile(), $section);
    }

    /**
     * A field is dehydrated only while its section is editable for the current
     * page type; disabled fields never reach the saved data.
     */
    private function dehydrateSection(string $section): \Closure
    {
        return fn (): bool => $this->sectionEditable($section);
    }

    private function disableSection(string $section): \Closure
    {
        return fn (): bool => ! $this->sectionEditable($section);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankCmsData(): array
    {
        return [
            'page_type' => 'cms',
            'title' => '',
            'title_en' => '',
            'slug' => '',
            'body' => '',
            'body_en' => '',
            'is_published' => false,
            'show_in_menu' => false,
            'show_in_footer' => false,
            'menu_order' => 0,
            'meta_title' => '',
            'meta_title_en' => '',
            'meta_description' => '',
            'meta_description_en' => '',
            'hero_logo' => null,
            'reset_hero_logo' => false,
            'main_image' => null,
            'reset_main_image' => false,
            'is_main_image_component_enabled' => true,
            'is_body_component_enabled' => true,
            'is_news_component_enabled' => true,
            'is_gallery_component_enabled' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initialData(): array
    {
        if ($this->pageRecord !== null) {
            return $this->cmsData($this->pageRecord);
        }

        return $this->systemData();
    }

    /**
     * @return array<string, mixed>
     */
    private function cmsData(Page $page): array
    {
        return array_merge($this->blankCmsData(), [
            'title' => (string) $page->title,
            'title_en' => (string) ($page->title_en ?? ''),
            'slug' => (string) $page->slug,
            'body' => (string) ($page->body ?? ''),
            'body_en' => (string) ($page->body_en ?? ''),
            'is_published' => (bool) $page->is_published,
            'show_in_menu' => (bool) $page->show_in_menu,
            'show_in_footer' => (bool) $page->show_in_footer,
            'menu_order' => (int) $page->menu_order,
            'meta_title' => (string) ($page->meta_title ?? ''),
            'meta_title_en' => (string) ($page->meta_title_en ?? ''),
            'meta_description' => (string) ($page->meta_description ?? ''),
            'meta_description_en' => (string) ($page->meta_description_en ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function systemData(): array
    {
        $profile = $this->profile();
        $provider = $this->provider();
        $editable = $provider->editable();

        $visibility = $profile['menuRoute'] !== null
            ? app(SiteNavigation::class)->routeVisibility((string) $profile['menuRoute'])
            : ['menu' => false, 'footer' => false];

        $data = array_merge($this->blankCmsData(), [
            'page_type' => $profile['key'],
            'title' => (string) ($editable['h1'] ?? ''),
            'title_en' => (string) ($editable['h1_en'] ?? ''),
            'slug' => (string) $profile['url'],
            'body' => (string) ($editable['body'] ?? ''),
            'body_en' => (string) ($editable['body_en'] ?? ''),
            'is_published' => true,
            'show_in_menu' => $visibility['menu'],
            'show_in_footer' => $visibility['footer'],
            'menu_order' => 0,
            'meta_title' => (string) ($editable['meta_title'] ?? ''),
            'meta_title_en' => (string) ($editable['meta_title_en'] ?? ''),
            'meta_description' => (string) ($editable['meta_description'] ?? ''),
            'meta_description_en' => (string) ($editable['meta_description_en'] ?? ''),
        ]);

        foreach ($profile['imageSlots'] as $slot) {
            $data[$slot['field']] = $editable[$slot['field']] ?? null;
            $data[$slot['resetField']] = false;
        }

        foreach ($profile['componentKeys'] as $component) {
            $data[$component['field']] = (bool) ($editable[$component['field']] ?? true);
        }

        return $data;
    }

    private function provider(): PageContentProvider
    {
        $class = $this->profile()['provider'] ?? null;

        abort_if($class === null, 404);

        $provider = app($class);

        abort_unless($provider instanceof PageContentProvider, 500);

        return $provider;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->typeSection(),
                $this->contentSection(),
                $this->slugSection(),
                $this->menuSection(),
                $this->seoSection(),
                $this->imagesSection(),
                $this->componentsSection(),
            ]);
    }

    private function typeSection(): Section
    {
        return Section::make(__('filament.page_editor.sections.type'))
            ->schema([
                Select::make('page_type')
                    ->label(__('filament.page_editor.fields.page_type'))
                    ->helperText(__('filament.page_editor.fields.page_type_help'))
                    ->options(fn (): array => $this->typeOptions())
                    ->selectablePlaceholder(false)
                    ->live()
                    ->required()
                    // The type is metadata of the editor, never a stored value.
                    ->dehydrated(false)
                    ->disabled(fn (): bool => ! $this->isCreateMode()),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        if (! $this->isCreateMode()) {
            $profile = PageEditorProfile::forKey($this->profileKey);

            return [$this->profileKey => (string) ($profile['title'] ?? $this->profileKey)];
        }

        $options = [];

        foreach (PageEditorProfile::profiles() as $key => $profile) {
            $options[$key] = PageEditorProfile::isCreatable($key)
                ? (string) $profile['title']
                : $profile['title'].' — '.__('filament.page_editor.system_readonly');
        }

        return $options;
    }

    private function contentSection(): Section
    {
        $enabled = fn (): bool => $this->sectionEditable(PageEditorProfile::SECTION_CONTENT);

        return Section::make(__('filament.page_editor.sections.content'))
            ->description(fn (): string => $this->sectionReason(PageEditorProfile::SECTION_CONTENT))
            ->disabled(fn (): bool => ! $enabled())
            ->schema([
                Tabs::make('content_translations')
                    ->tabs([
                        Tab::make(__('filament.resources.pages.tabs.ru'))
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('filament.page_editor.fields.title'))
                                    ->required(fn (): bool => $enabled())
                                    ->maxLength(150)
                                    ->disabled(fn (): bool => ! $enabled())
                                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_CONTENT)),
                                MarkdownEditor::make('body')
                                    ->label(__('filament.page_editor.fields.body'))
                                    ->helperText(__('filament.page_editor.fields.body_help'))
                                    ->columnSpanFull()
                                    ->disabled(fn (): bool => ! $enabled())
                                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_CONTENT)),
                            ])
                            ->columns(2),
                        Tab::make(__('filament.resources.pages.tabs.en'))
                            ->schema([
                                TextInput::make('title_en')
                                    ->label(__('filament.page_editor.fields.title_en'))
                                    ->maxLength(150)
                                    ->disabled(fn (): bool => ! $enabled())
                                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_CONTENT)),
                                MarkdownEditor::make('body_en')
                                    ->label(__('filament.page_editor.fields.body_en'))
                                    ->helperText(__('filament.page_editor.fields.body_en_help'))
                                    ->columnSpanFull()
                                    ->disabled(fn (): bool => ! $enabled())
                                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_CONTENT)),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private function slugSection(): Section
    {
        return Section::make(__('filament.page_editor.sections.slug'))
            ->description(fn (): string => $this->sectionReason(PageEditorProfile::SECTION_SLUG))
            ->disabled($this->disableSection(PageEditorProfile::SECTION_SLUG))
            ->schema([
                TextInput::make('slug')
                    ->label(__('filament.resources.pages.fields.slug'))
                    ->helperText(fn (): string => $this->sectionEditable(PageEditorProfile::SECTION_SLUG)
                        ? __('filament.page_editor.fields.slug_help')
                        : $this->sectionReason(PageEditorProfile::SECTION_SLUG))
                    ->maxLength(120)
                    ->rules(function (): array {
                        // A system page has a fixed route: its slug is read-only
                        // and must not be validated as a CMS slug.
                        if (! $this->sectionEditable(PageEditorProfile::SECTION_SLUG)) {
                            return [];
                        }

                        // Only active pages compete for a slug: a soft-deleted
                        // page releases its slug (see the `slug_key` column).
                        $unique = Rule::unique('pages', 'slug')->withoutTrashed();

                        if ($this->pageRecord !== null) {
                            $unique->ignore($this->pageRecord->getKey());
                        }

                        return [
                            'nullable',
                            'string',
                            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                            Rule::notIn(PageController::RESERVED_SLUGS),
                            // `notIn` cannot see prefixes: a slug such as
                            // `livewire-account` would pass it and then shadow
                            // the Livewire asset endpoint on the public route.
                            function (string $attribute, mixed $value, \Closure $fail): void {
                                if (is_string($value) && Page::isReservedSlug($value)) {
                                    $fail(__('filament.page_editor.validation.slug_reserved'));
                                }
                            },
                            $unique,
                        ];
                    })
                    ->validationMessages([
                        'regex' => __('filament.page_editor.validation.slug_format'),
                        'not_in' => __('filament.page_editor.validation.slug_reserved'),
                    ])
                    ->disabled($this->disableSection(PageEditorProfile::SECTION_SLUG))
                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_SLUG)),
            ]);
    }

    private function menuSection(): Section
    {
        $menuEnabled = fn (): bool => $this->sectionEditable(PageEditorProfile::SECTION_MENU);

        // Publication belongs to CMS pages only: a system page is a fixed
        // route that always exists, so it cannot be published or hidden.
        $publicationEnabled = fn (): bool => ($this->profile()['kind'] ?? null) === PageEditorProfile::KIND_CMS;
        $isHome = fn (): bool => ($this->profile()['key'] ?? null) === 'home';

        return Section::make(__('filament.page_editor.sections.menu'))
            ->description(fn (): string => $this->sectionReason(PageEditorProfile::SECTION_MENU))
            ->schema([
                Toggle::make('is_published')
                    ->label(__('filament.resources.pages.fields.is_published'))
                    ->helperText(fn (): string => $publicationEnabled()
                        ? ''
                        : __('filament.page_editor.fields.system_published_help'))
                    ->disabled(fn (): bool => ! $publicationEnabled())
                    ->dehydrated(fn (): bool => $publicationEnabled()),
                Toggle::make('show_in_menu')
                    ->label(__('filament.resources.pages.fields.show_in_menu'))
                    ->helperText(fn (): string => $isHome()
                        ? __('filament.page_editor.fields.home_menu_help')
                        : '')
                    ->disabled(fn (): bool => ! $menuEnabled())
                    ->dehydrated(fn (): bool => $menuEnabled()),
                Toggle::make('show_in_footer')
                    ->label(__('filament.resources.pages.fields.show_in_footer'))
                    ->disabled(fn (): bool => ! $menuEnabled())
                    ->dehydrated(fn (): bool => $menuEnabled()),
                TextInput::make('menu_order')
                    ->label(__('filament.resources.pages.fields.menu_order'))
                    ->helperText(__('filament.page_editor.fields.menu_order_help'))
                    ->numeric()
                    ->default(0)
                    ->disabled(fn (): bool => ! $publicationEnabled())
                    ->dehydrated(fn (): bool => $publicationEnabled()),
            ])
            ->columns(2);
    }

    private function seoSection(): Section
    {
        $enabled = fn (): bool => $this->sectionEditable(PageEditorProfile::SECTION_SEO);

        return Section::make(__('filament.page_editor.sections.seo'))
            ->description(fn (): string => $this->sectionReason(PageEditorProfile::SECTION_SEO))
            ->disabled(fn (): bool => ! $enabled())
            ->schema([
                Tabs::make('seo_translations')
                    ->tabs([
                        Tab::make(__('filament.resources.pages.tabs.ru'))
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label(__('filament.resources.pages.fields.meta_title'))
                                    ->maxLength(180)
                                    ->disabled(fn (): bool => ! $enabled())
                                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_SEO)),
                                TextInput::make('meta_description')
                                    ->label(__('filament.resources.pages.fields.meta_description'))
                                    ->maxLength(255)
                                    ->disabled(fn (): bool => ! $enabled())
                                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_SEO)),
                            ])
                            ->columns(2),
                        Tab::make(__('filament.resources.pages.tabs.en'))
                            ->schema([
                                TextInput::make('meta_title_en')
                                    ->label(__('filament.resources.pages.fields.meta_title_en'))
                                    ->maxLength(180)
                                    ->disabled(fn (): bool => ! $enabled())
                                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_SEO)),
                                TextInput::make('meta_description_en')
                                    ->label(__('filament.resources.pages.fields.meta_description_en'))
                                    ->maxLength(255)
                                    ->disabled(fn (): bool => ! $enabled())
                                    ->dehydrated($this->dehydrateSection(PageEditorProfile::SECTION_SEO)),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private function imagesSection(): Section
    {
        $fields = [];

        foreach ([
            ['field' => 'hero_logo', 'label' => __('filament.page_editor.fields.hero_logo')],
            ['field' => 'main_image', 'label' => __('filament.page_editor.fields.main_image')],
        ] as $definition) {
            $field = $definition['field'];
            $enabled = fn (): bool => $this->slotEnabled($field);

            $fields[] = FileUpload::make($field)
                ->label($definition['label'])
                ->helperText(fn (): string => $enabled()
                    ? __('filament.page_editor.fields.image_help')
                    : __('filament.page_editor.not_applicable'))
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(4096)
                ->disk('public')
                // Re-encoded to WebP by HomeImageUploader in save().
                ->storeFiles(false)
                ->deletable(false)
                ->disabled(fn (): bool => ! $enabled())
                ->dehydrated(fn (): bool => $enabled());

            $fields[] = Toggle::make('reset_'.$field)
                ->label(__('filament.page_editor.fields.reset_image'))
                ->helperText(__('filament.page_editor.fields.reset_image_help'))
                ->visible(fn (Get $get): bool => $this->slotEnabled($field) && filled($get($field)))
                ->disabled(fn (): bool => ! $enabled())
                ->dehydrated(fn (): bool => $enabled());
        }

        return Section::make(__('filament.page_editor.sections.images'))
            ->description(fn (): string => $this->sectionReason(PageEditorProfile::SECTION_IMAGES))
            ->disabled($this->disableSection(PageEditorProfile::SECTION_IMAGES))
            ->schema($fields)
            ->columns(2);
    }

    private function componentsSection(): Section
    {
        $fields = [];

        foreach ([
            ['field' => 'is_main_image_component_enabled', 'label' => __('filament.home.fields.is_main_image_component_enabled')],
            ['field' => 'is_body_component_enabled', 'label' => __('filament.home.fields.is_body_component_enabled')],
            ['field' => 'is_news_component_enabled', 'label' => __('filament.news_page.fields.is_news_component_enabled')],
            ['field' => 'is_gallery_component_enabled', 'label' => __('filament.gallery_page.fields.is_gallery_component_enabled')],
        ] as $definition) {
            $field = $definition['field'];
            $enabled = fn (): bool => $this->componentEnabled($field);

            $fields[] = Toggle::make($field)
                ->label($definition['label'])
                ->helperText(fn (): string => $enabled()
                    ? ''
                    : __('filament.page_editor.not_applicable'))
                ->disabled(fn (): bool => ! $enabled())
                ->dehydrated(fn (): bool => $enabled());
        }

        return Section::make(__('filament.page_editor.sections.components'))
            ->description(fn (): string => $this->sectionReason(PageEditorProfile::SECTION_COMPONENTS))
            ->disabled($this->disableSection(PageEditorProfile::SECTION_COMPONENTS))
            ->schema($fields)
            ->columns(2);
    }

    private function slotEnabled(string $field): bool
    {
        if (! $this->sectionEditable(PageEditorProfile::SECTION_IMAGES)) {
            return false;
        }

        foreach ($this->profile()['imageSlots'] as $slot) {
            if ($slot['field'] === $field) {
                return true;
            }
        }

        return false;
    }

    private function componentEnabled(string $field): bool
    {
        if (! $this->sectionEditable(PageEditorProfile::SECTION_COMPONENTS)) {
            return false;
        }

        foreach ($this->profile()['componentKeys'] as $component) {
            if ($component['field'] !== $field) {
                continue;
            }

            if ($component['adminOnly'] && ! (auth()->user()?->isAdmin() ?? false)) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        if ($this->isCreateMode() || $this->pageRecord === null) {
            return [];
        }

        return [
            PageResource::deleteAction()
                ->record(fn (): Page => $this->pageRecord)
                ->successRedirectUrl(PageResource::getUrl('index')),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return PageResource::canDelete($record);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->authorizeProfile();

        // A system page already exists as a fixed route, so "creating" one
        // opens its editor instead of writing a duplicate.
        if ($this->isCreateMode() && ! PageEditorProfile::isCreatable($this->currentType())) {
            Notification::make()
                ->info()
                ->title(__('filament.page_editor.system_redirect'))
                ->send();

            $this->redirect(EditPage::getUrl(['record' => 'sys.'.$this->currentType()]));

            return;
        }

        $data = $this->form->getState();

        if ($this->pageRecord !== null || $this->isCreateMode()) {
            $this->saveCms($data);

            return;
        }

        $this->saveSystem($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveCms(array $data): void
    {
        abort_unless(PageResource::canCreate(), 403);

        $page = $this->pageRecord ?? new Page;

        $values = [
            'title' => trim((string) ($data['title'] ?? '')),
            'title_en' => $this->nullable($data['title_en'] ?? null),
            'slug' => trim((string) ($data['slug'] ?? '')),
            'body' => is_string($data['body'] ?? null) ? $data['body'] : '',
            'body_en' => $this->nullable($data['body_en'] ?? null),
            'is_published' => (bool) ($data['is_published'] ?? false),
            'show_in_menu' => (bool) ($data['show_in_menu'] ?? false),
            'show_in_footer' => (bool) ($data['show_in_footer'] ?? false),
            'menu_order' => (int) ($data['menu_order'] ?? 0),
            'meta_title' => $this->nullable($data['meta_title'] ?? null),
            'meta_title_en' => $this->nullable($data['meta_title_en'] ?? null),
            'meta_description' => $this->nullable($data['meta_description'] ?? null),
            'meta_description_en' => $this->nullable($data['meta_description_en'] ?? null),
        ];

        // The `unique` rule above is checked before the write, so it cannot see
        // a row committed in between, and an empty slug is only resolved to a
        // value by the model. Either way the index is the last line of defence:
        // translate its violation into a field error instead of an HTTP 500.
        try {
            $this->pageRecord = app(PageMenuManager::class)->save($page, $values);
        } catch (SlugGenerationException $e) {
            report($e);

            throw ValidationException::withMessages([
                'data.slug' => __('filament.page_editor.validation.slug_auto'),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains((string) $e->index, 'pages_slug_key_unique')) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'data.slug' => __('filament.page_editor.validation.slug_taken'),
            ]);
        }

        Notification::make()
            ->success()
            ->title(__('filament.page_editor.saved'))
            ->send();

        if ($this->isCreateMode()) {
            $this->redirect(EditPage::getUrl(['record' => 'cms-'.$this->pageRecord->getKey()]));

            return;
        }

        $this->data = $this->cmsData($this->pageRecord);
        $this->form->fill($this->data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveSystem(array $data): void
    {
        $profile = $this->profile();
        $providerClass = (string) $profile['provider'];

        $settings = app(SettingsRepository::class);
        $uploader = app(HomeImageUploader::class);
        $audit = app(AuditLogger::class);

        // Files are tracked from the moment they reach the disk, so that no exit
        // path — including a failure before the transaction is even opened — can
        // leave an orphan behind.
        $images = ['values' => [], 'stored' => [], 'replaced' => []];

        try {
            $images = $this->resolveImages($profile, $providerClass, $data, $settings, $uploader);

            $values = [
                $this->key($providerClass, 'KEY_META_TITLE') => trim((string) ($data['meta_title'] ?? '')),
                $this->key($providerClass, 'KEY_META_TITLE_EN') => trim((string) ($data['meta_title_en'] ?? '')),
                $this->key($providerClass, 'KEY_META_DESCRIPTION') => trim((string) ($data['meta_description'] ?? '')),
                $this->key($providerClass, 'KEY_META_DESCRIPTION_EN') => trim((string) ($data['meta_description_en'] ?? '')),
                $this->key($providerClass, 'KEY_H1') => trim((string) ($data['title'] ?? '')),
                $this->key($providerClass, 'KEY_H1_EN') => trim((string) ($data['title_en'] ?? '')),
                $this->key($providerClass, 'KEY_BODY') => is_string($data['body'] ?? null) ? trim($data['body']) : '',
                $this->key($providerClass, 'KEY_BODY_EN') => is_string($data['body_en'] ?? null) ? trim($data['body_en']) : '',
            ];

            foreach ($profile['componentKeys'] as $component) {
                if (! $this->componentEnabled($component['field'])) {
                    continue;
                }

                $values[$this->key($providerClass, $component['key'])] = (bool) ($data[$component['field']] ?? true);
            }

            $values += $images['values'];

            $previous = [];
            foreach (array_keys($values) as $key) {
                $previous[$key] = $settings->get($key);
            }

            // Settings, audit and navigation are one unit: a failure in any of
            // them must leave the stored content untouched and discard the new
            // files.
            DB::transaction(function () use ($values, $previous, $profile, $audit, $settings, $data): void {
                $settings->setMany($values);

                $audit->log(
                    action: (string) $profile['auditAction'],
                    old: $previous,
                    new: $values,
                    recordIp: false,
                );

                if ($profile['menuRoute'] !== null) {
                    app(PageMenuManager::class)->setRouteVisibility(
                        route: (string) $profile['menuRoute'],
                        showInMenu: (bool) ($data['show_in_menu'] ?? false),
                        showInFooter: (bool) ($data['show_in_footer'] ?? false),
                        labelRu: $this->menuLabel($profile, 'ru'),
                        labelEn: $this->menuLabel($profile, 'en'),
                    );
                }
            });
        } catch (ImageProcessingException $e) {
            $this->forgetFiles($images['stored'], $uploader);
            $settings->flush();

            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            return;
        } catch (Throwable $e) {
            $this->forgetFiles($images['stored'], $uploader);
            $settings->flush();
            report($e);

            Notification::make()
                ->danger()
                ->title(__('filament.page_editor.save_failed'))
                ->send();

            return;
        }

        // Replaced files are removed only after the commit: until then the
        // stored setting still points at them.
        $this->forgetFiles($images['replaced'], $uploader);

        $settings->flush();

        Notification::make()
            ->success()
            ->title(__('filament.page_editor.saved'))
            ->send();

        $this->data = $this->systemData();
        $this->form->fill($this->data);
    }

    /**
     * @param  array<int, ?string>  $paths
     */
    private function forgetFiles(array $paths, HomeImageUploader $uploader): void
    {
        foreach ($paths as $path) {
            $uploader->remove($path);
        }
    }

    /**
     * Localized RU/EN label of a system page in the navigation menus.
     *
     * @param  array<string, mixed>  $profile
     */
    private function menuLabel(array $profile, string $locale): string
    {
        $key = $profile['menuLabelKey'] ?? null;

        if (is_string($key) && $key !== '') {
            return (string) trans($key, [], $locale);
        }

        return (string) $profile['title'];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $data
     * @return array{values: array<string, mixed>, stored: array<int, string>, replaced: array<int, ?string>}
     *
     * Any failure after a file reached the disk removes the files stored so far
     * and rethrows: the caller only ever sees an exception, never an orphan.
     *
     * @throws ImageProcessingException
     */
    private function resolveImages(
        array $profile,
        string $providerClass,
        array $data,
        SettingsRepository $settings,
        HomeImageUploader $uploader,
    ): array {
        $values = [];
        $stored = [];
        $replaced = [];

        try {
            foreach ($profile['imageSlots'] as $slot) {
                $field = (string) $slot['field'];
                $pathKey = $this->key($providerClass, (string) $slot['pathKey']);

                $current = $settings->get($pathKey);
                $current = is_string($current) && trim($current) !== '' ? $current : null;

                if ((bool) ($data[$slot['resetField']] ?? false)) {
                    // The file is deleted only after the settings commit.
                    $replaced[] = $current;

                    $values[$pathKey] = null;
                    $this->resetDimensions($values, $providerClass, $slot);

                    continue;
                }

                $file = $this->uploadedFile($data[$field] ?? null);

                if ($file !== null) {
                    $uploaded = $uploader->upload($file, (string) $slot['slot']);

                    $stored[] = $uploaded['path'];
                    $replaced[] = $current;

                    $values[$pathKey] = $uploaded['path'];
                    $this->storeDimensions($values, $providerClass, $slot, $uploaded['width'], $uploaded['height']);

                    continue;
                }

                // Unchanged: keep only the path that was placed into the form.
                $submitted = $this->submittedPath($data[$field] ?? null);
                $values[$pathKey] = $submitted !== null && $submitted === $current ? $submitted : null;
            }
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $uploader->remove($path);
            }

            throw $e;
        }

        return ['values' => $values, 'stored' => $stored, 'replaced' => $replaced];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $slot
     */
    private function storeDimensions(array &$values, string $providerClass, array $slot, int $width, int $height): void
    {
        if ($slot['widthKey'] === null || $slot['heightKey'] === null) {
            return;
        }

        $values[$this->key($providerClass, (string) $slot['widthKey'])] = $width;
        $values[$this->key($providerClass, (string) $slot['heightKey'])] = $height;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $slot
     */
    private function resetDimensions(array &$values, string $providerClass, array $slot): void
    {
        if ($slot['widthKey'] === null || $slot['heightKey'] === null) {
            return;
        }

        $values[$this->key($providerClass, (string) $slot['widthKey'])] = null;
        $values[$this->key($providerClass, (string) $slot['heightKey'])] = null;
    }

    /**
     * Resolve a provider constant by name.
     *
     * A missing constant means the profile and the provider disagree, which is
     * a programming error: silently returning an empty setting key would write
     * a row with an empty key instead of failing loudly.
     */
    private function key(string $providerClass, string $constant): string
    {
        $name = $providerClass.'::'.$constant;

        if (! defined($name)) {
            throw new RuntimeException(sprintf(
                'Page content provider %s does not define the %s constant.',
                $providerClass,
                $constant,
            ));
        }

        return (string) constant($name);
    }

    private function nullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

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
