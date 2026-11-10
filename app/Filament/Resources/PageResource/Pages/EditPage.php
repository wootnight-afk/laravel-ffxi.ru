<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageResource\Pages;

/**
 * Edit entry point of the unified page editor (`/admin/pages/{record}/edit`).
 *
 * The `record` segment is a page key:
 *
 *  - `cms-{id}` — a CMS page stored in the `pages` table;
 *  - `sys.{route}` — a system page of the registry (e.g. `sys.news.index`).
 *
 * All form building and saving lives in {@see SitePageEditor}.
 */
class EditPage extends SitePageEditor
{
    protected function isCreateMode(): bool
    {
        return false;
    }
}
