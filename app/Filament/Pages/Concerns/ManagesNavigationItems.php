<?php

declare(strict_types=1);

namespace App\Filament\Pages\Concerns;

use App\Exceptions\ImageProcessingException;
use App\Filament\Pages\HeaderPage;
use App\Models\Page;
use App\Services\HomeImageUploader;
use App\Services\SiteNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Shared Repeater and normalisation logic of the Header/Footer CMS editors
 * (ADR-011 §2.22–2.23).
 *
 * Items are stored in the `settings` key/value store as a JSON list with the
 * canonical shape `{id, label_ru, label_en, type, target, visible, image}`.
 * The form uses one field per target kind (`route`, `page`, `url`) because a
 * single state key cannot back both a Select and a free-text input.
 */
trait ManagesNavigationItems
{
    protected function navigationRepeater(): Repeater
    {
        return Repeater::make('items')
            ->label(__('filament.navigation_items.label'))
            ->helperText(__('filament.navigation_items.help'))
            ->schema([
                Hidden::make('id'),
                TextInput::make('label_ru')
                    ->label(__('filament.navigation_items.fields.label_ru'))
                    ->maxLength(60),
                TextInput::make('label_en')
                    ->label(__('filament.navigation_items.fields.label_en'))
                    ->helperText(__('filament.navigation_items.fields.label_en_help'))
                    ->maxLength(60),
                Select::make('type')
                    ->label(__('filament.navigation_items.fields.type'))
                    ->options([
                        SiteNavigation::TYPE_ROUTE => __('filament.navigation_items.types.route'),
                        SiteNavigation::TYPE_PAGE => __('filament.navigation_items.types.page'),
                        SiteNavigation::TYPE_URL => __('filament.navigation_items.types.url'),
                    ])
                    ->default(SiteNavigation::TYPE_ROUTE)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Select::make('route')
                    ->label(__('filament.navigation_items.fields.route'))
                    ->options(fn (): array => $this->routeOptions())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('type') === SiteNavigation::TYPE_ROUTE),
                Select::make('page')
                    ->label(__('filament.navigation_items.fields.page'))
                    ->options(fn (): array => $this->pageOptions())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('type') === SiteNavigation::TYPE_PAGE),
                TextInput::make('url')
                    ->label(__('filament.navigation_items.fields.url'))
                    ->helperText(__('filament.navigation_items.fields.url_help'))
                    ->url()
                    ->maxLength(2048)
                    ->visible(fn (Get $get): bool => $get('type') === SiteNavigation::TYPE_URL),
                Toggle::make('visible')
                    ->label(__('filament.navigation_items.fields.visible'))
                    ->default(true),
                FileUpload::make('image')
                    ->label(__('filament.navigation_items.fields.image'))
                    ->helperText(__('filament.navigation_items.fields.image_help'))
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->disk('public')
                    // Re-encoded to WebP by HomeImageUploader in save().
                    ->storeFiles(false),
            ])
            ->deleteAction(function (Action $action): Action {
                if (! $this instanceof HeaderPage) {
                    return $action;
                }

                return $action->label(__('filament.navigation_items.hide'))
                    ->icon(null)->button()->color('gray')
                    ->action(function (array $arguments, Repeater $component): void {
                        $items = $component->getRawState();
                        $items[$arguments['item']]['visible'] = false;
                        $component->rawState($items);
                        $component->callAfterStateUpdated();
                    });
            })
            ->columns(2)
            ->reorderableWithButtons()
            ->collapsible()
            ->collapsed()
            ->addActionLabel(__('filament.navigation_items.add'))
            ->itemLabel(fn (array $state): ?string => filled($state['label_ru'] ?? null)
                ? (string) $state['label_ru']
                : null);
    }

    /**
     * Routes that may be used as a navigation target.
     *
     * @return array<string, string>
     */
    protected function routeOptions(): array
    {
        /** @var array<string, string> $labels */
        $labels = [
            'home' => __('site.nav.home'),
            'news.index' => __('site.nav.news'),
            'gallery.index' => __('site.nav.gallery'),
            'players.dashboard' => __('site.nav.players'),
            'contacts' => __('site.nav.contacts'),
            'events.index' => __('site.nav.events'),
            'login' => __('site.nav.login'),
            'register' => __('site.nav.register'),
        ];

        $options = [];

        foreach (SiteNavigation::ROUTES as $route) {
            if (Route::has($route)) {
                $options[$route] = $labels[$route] ?? $route;
            }
        }

        return $options;
    }

    /**
     * Published CMS pages that may be used as a navigation target.
     *
     * @return array<string, string>
     */
    protected function pageOptions(): array
    {
        return Page::query()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * Convert stored items into Repeater state.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function fillItems(array $items): array
    {
        return array_map(function (array $item): array {
            $type = $item['type'] ?? SiteNavigation::TYPE_ROUTE;
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';

            return [
                'id' => (string) ($item['id'] ?? ''),
                'label_ru' => (string) ($item['label_ru'] ?? ''),
                'label_en' => (string) ($item['label_en'] ?? ''),
                'type' => $type,
                'route' => $type === SiteNavigation::TYPE_ROUTE ? $target : null,
                'page' => $type === SiteNavigation::TYPE_PAGE ? $target : null,
                'url' => $type === SiteNavigation::TYPE_URL ? $target : null,
                'visible' => $type === SiteNavigation::TYPE_PAGE
                    ? (bool) Page::find((int) $target)?->getAttribute($this instanceof HeaderPage ? 'show_in_menu' : 'show_in_footer')
                    : (bool) ($item['visible'] ?? true),
                'image' => is_string($item['image'] ?? null) && trim($item['image']) !== ''
                    ? $item['image']
                    : null,
            ];
        }, $items);
    }

    /**
     * Convert Repeater state back into the canonical stored shape.
     *
     * Rows without a label or without a target are dropped: an incomplete row
     * must never break the public layout.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $previous
     * @return array<int, array<string, mixed>>
     *
     * @throws ImageProcessingException
     */
    protected function buildItems(array $rows, array $previous, HomeImageUploader $uploader): array
    {
        $previousById = [];

        foreach ($previous as $item) {
            $previousById[(string) ($item['id'] ?? '')] = $item;
        }

        $items = [];

        foreach ($rows as $row) {
            $type = $this->resolveType($row['type'] ?? null);
            $target = $this->resolveTarget($row, $type);

            if ($target === null) {
                continue;
            }

            $labelRu = trim((string) ($row['label_ru'] ?? ''));

            if ($labelRu === '') {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            $previousItem = $id !== '' ? ($previousById[$id] ?? null) : null;
            $previousImage = is_string($previousItem['image'] ?? null) && trim($previousItem['image']) !== ''
                ? $previousItem['image']
                : null;

            $items[] = [
                'id' => $id !== '' ? $id : Str::random(8),
                'label_ru' => $labelRu,
                'label_en' => trim((string) ($row['label_en'] ?? '')),
                'type' => $type,
                'target' => $target,
                'visible' => (bool) ($row['visible'] ?? true),
                // The Repeater order is persisted explicitly (ADR-011 §8.2).
                'order' => count($items),
                'image' => $this->resolveImage($row['image'] ?? null, $previousImage, $uploader),
            ];
        }

        return $items;
    }

    private function resolveType(mixed $type): string
    {
        return in_array($type, [
            SiteNavigation::TYPE_ROUTE,
            SiteNavigation::TYPE_PAGE,
            SiteNavigation::TYPE_URL,
        ], true) ? $type : SiteNavigation::TYPE_ROUTE;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveTarget(array $row, string $type): ?string
    {
        $value = match ($type) {
            SiteNavigation::TYPE_ROUTE => $row['route'] ?? null,
            SiteNavigation::TYPE_PAGE => $row['page'] ?? null,
            SiteNavigation::TYPE_URL => $row['url'] ?? null,
            default => null,
        };

        if ($type === SiteNavigation::TYPE_PAGE && is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * Store a new icon, keep the current one or delete a removed one.
     *
     * @throws ImageProcessingException
     */
    private function resolveImage(mixed $value, ?string $previous, HomeImageUploader $uploader): ?string
    {
        $file = $this->uploadedFile($value);

        if ($file !== null) {
            $stored = $uploader->upload($file, HomeImageUploader::SLOT_NAV);
            $uploader->remove($previous);

            return $stored['path'];
        }

        $path = is_string($value) ? trim($value) : '';

        // Only the path that was placed into the form may be kept; anything
        // else (cleared field, tampered value) resets the icon.
        if ($path !== '' && $path === $previous) {
            return $path;
        }

        $uploader->remove($previous);

        return null;
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
}
