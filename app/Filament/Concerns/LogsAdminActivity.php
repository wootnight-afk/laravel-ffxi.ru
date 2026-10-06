<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Support\Str;

/**
 * Marks a Filament resource as auditable and defines its audit metadata.
 *
 * Filament 5 exposes lifecycle hooks on pages (not on the resource class), so
 * create/update events are recorded through the `RecordCreated`/`RecordUpdated`
 * events and deletions through `DeleteAction`, both wired in
 * `AdminActivityServiceProvider`. The resource trait carries the policy that
 * those listeners apply.
 */
trait LogsAdminActivity
{
    /**
     * Action code prefix, e.g. `news` for `NewsResource`.
     */
    public static function activityLogKey(): string
    {
        return Str::snake(class_basename(static::getModel()));
    }

    /**
     * Attributes replaced by `[changed]` in the audit payload.
     *
     * @return array<int, string>
     */
    public static function activityLogRedactedAttributes(): array
    {
        return ['email', 'password', 'phone'];
    }

    /**
     * Attributes that must never be written to the audit payload.
     *
     * @return array<int, string>
     */
    public static function activityLogIgnoredAttributes(): array
    {
        return [
            'remember_token',
            'password_confirmation',
            'app_authentication_secret',
            'app_authentication_recovery_codes',
        ];
    }
}
