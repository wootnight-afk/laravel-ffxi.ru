<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Contract for the settings-backed content of a system page.
 *
 * Implementations expose the values shaped for the unified page editor and for
 * the public view. The editor only relies on {@see editable()}; the public
 * controllers consume {@see view()}.
 */
interface PageContentProvider
{
    /**
     * Current values shaped for the admin form.
     *
     * @return array<string, mixed>
     */
    public function editable(): array;

    /**
     * Everything the public view needs, already resolved.
     *
     * @return array<string, mixed>
     */
    public function view(): array;
}
