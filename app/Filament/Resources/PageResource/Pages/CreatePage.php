<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageResource\Pages;

/**
 * Create entry point of the unified page editor (`/admin/pages/create`).
 *
 * All form building and saving lives in {@see SitePageEditor}; this class only
 * marks the route as the create mode.
 */
class CreatePage extends SitePageEditor
{
    protected function isCreateMode(): bool
    {
        return true;
    }
}
