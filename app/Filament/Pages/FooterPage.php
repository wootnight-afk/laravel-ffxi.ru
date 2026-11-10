<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Exceptions\ImageProcessingException;
use App\Filament\Pages\Concerns\ManagesNavigationItems;
use App\Services\AuditLogger;
use App\Services\HomeImageUploader;
use App\Services\PageMenuManager;
use App\Services\SettingsRepository;
use App\Services\SiteNavigation;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * CMS editor of the public footer (ADR-011 §2.23): RU/EN text and links.
 *
 * @property-read Schema $form
 */
class FooterPage extends Page
{
    use ManagesNavigationItems;

    protected string $view = 'filament.pages.footer-page';

    protected static ?string $slug = 'footer';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationParentItem = 'content-menus';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('pages.manage') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.footer.label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'content';
    }

    public function getTitle(): string
    {
        return __('filament.footer.title');
    }

    public function mount(SiteNavigation $navigation): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'text' => $navigation->editableFooterText(),
            'items' => $this->fillItems($navigation->editableFooter()),
        ]);
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
                Section::make(__('filament.footer.sections.text'))
                    ->schema([
                        Tabs::make('footer_text_translations')
                            ->tabs([
                                Tab::make(__('filament.footer.tabs.ru'))
                                    ->schema([
                                        TextInput::make('text.text_ru')
                                            ->label(__('filament.footer.fields.text_ru'))
                                            ->required()
                                            ->maxLength(255),
                                    ]),
                                Tab::make(__('filament.footer.tabs.en'))
                                    ->schema([
                                        TextInput::make('text.text_en')
                                            ->label(__('filament.footer.fields.text_en'))
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
                Section::make(__('filament.footer.sections.links'))
                    ->schema([
                        $this->navigationRepeater(),
                    ]),
            ]);
    }

    public function save(
        SiteNavigation $navigation,
        HomeImageUploader $uploader,
        SettingsRepository $settings,
        AuditLogger $audit,
    ): void {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        try {
            $items = $this->buildItems(
                rows: is_array($data['items'] ?? null) ? $data['items'] : [],
                previous: $navigation->editableFooter(),
                uploader: $uploader,
            );
        } catch (ImageProcessingException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            return;
        }

        $text = is_array($data['text'] ?? null) ? $data['text'] : [];

        $values = [
            SiteNavigation::KEY_FOOTER_TEXT_RU => trim((string) ($text['text_ru'] ?? '')),
            SiteNavigation::KEY_FOOTER_TEXT_EN => trim((string) ($text['text_en'] ?? '')),
            SiteNavigation::KEY_FOOTER_ITEMS => $items,
        ];

        unset($values[SiteNavigation::KEY_FOOTER_ITEMS]);
        app(PageMenuManager::class)->saveMenu(SiteNavigation::KEY_FOOTER_ITEMS, $items, $values);

        Notification::make()
            ->success()
            ->title(__('filament.footer.saved'))
            ->send();

        $this->mount($navigation);
    }
}
