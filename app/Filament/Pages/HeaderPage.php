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
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * CMS editor of the public header navigation (ADR-011 §2.22).
 *
 * Only the CMS items are editable here. The system controls of the header
 * (language/theme switchers, activity bell, profile, sign-in/out) are rendered
 * by the layout and stay functional regardless of this configuration.
 *
 * @property-read Schema $form
 */
class HeaderPage extends Page
{
    use ManagesNavigationItems;

    protected string $view = 'filament.pages.header-page';

    protected static ?string $slug = 'header';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 0;

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
        return __('filament.header.label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'content';
    }

    public function getTitle(): string
    {
        return __('filament.header.title');
    }

    public function mount(SiteNavigation $navigation): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'items' => $this->fillItems($navigation->editableHeader()),
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
                Section::make(__('filament.header.section'))
                    ->description(__('filament.header.description'))
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
                previous: $navigation->editableHeader(),
                uploader: $uploader,
            );
        } catch (ImageProcessingException $e) {
            // Nothing is written when an upload is rejected.
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            return;
        }

        app(PageMenuManager::class)->saveMenu(SiteNavigation::KEY_HEADER_ITEMS, $items);

        Notification::make()
            ->success()
            ->title(__('filament.header.saved'))
            ->send();

        $this->mount($navigation);
    }
}
