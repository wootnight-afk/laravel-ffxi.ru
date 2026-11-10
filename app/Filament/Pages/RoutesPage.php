<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\SitePageRegistry;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Read-only reference of every registered route and its classification.
 *
 * Administrative pages are marked "Filament", endpoints "API" and the
 * application plumbing "Technical", so it is explicit which routes are pages of
 * the public site and which are management tools or infrastructure. The page
 * does not change any route: it only reads the route table.
 *
 * @property-read array<int, array<string, mixed>> $routes
 */
class RoutesPage extends Page
{
    protected string $view = 'filament.pages.routes-page';

    protected static ?string $slug = 'routes';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.routes_page.label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'system';
    }

    public function getTitle(): string
    {
        return __('filament.routes_page.title');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRoutesProperty(): array
    {
        return app(SitePageRegistry::class)->classification();
    }

    /**
     * @return array<string, string>
     */
    public function getCategoryLabels(): array
    {
        return [
            'public' => __('filament.routes_page.categories.public'),
            'auth' => __('filament.routes_page.categories.auth'),
            'filament' => __('filament.routes_page.categories.filament'),
            'api' => __('filament.routes_page.categories.api'),
            'technical' => __('filament.routes_page.categories.technical'),
            'write' => __('filament.routes_page.categories.write'),
            'missing' => __('filament.routes_page.categories.missing'),
            'unclassified' => __('filament.routes_page.categories.unclassified'),
        ];
    }
}
