<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Concerns\LogsAdminActivity;
use App\Services\AdminActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Events\RecordCreated;
use Filament\Resources\Events\RecordUpdated;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires admin mutation auditing for every resource using {@see LogsAdminActivity}.
 */
class AdminActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(RecordCreated::class, function (Model $record, array $data, Page $page): void {
            $resource = $page::getResource();

            if (! $this->isAuditable($resource)) {
                return;
            }

            app(AdminActivityLogger::class)->record(
                subject: $record,
                verb: 'created',
                new: $record->getAttributes(),
                key: $resource::activityLogKey(),
                redacted: $resource::activityLogRedactedAttributes(),
                ignored: $resource::activityLogIgnoredAttributes(),
            );
        });

        Event::listen(RecordUpdated::class, function (Model $record, array $data, Page $page): void {
            $resource = $page::getResource();

            if (! $this->isAuditable($resource)) {
                return;
            }

            app(AdminActivityLogger::class)->record(
                subject: $record,
                verb: 'updated',
                new: $record->getChanges(),
                key: $resource::activityLogKey(),
                redacted: $resource::activityLogRedactedAttributes(),
                ignored: $resource::activityLogIgnoredAttributes(),
            );
        });

        DeleteAction::configureUsing(function (DeleteAction $action): void {
            $action->after(function (Model $record): void {
                app(AdminActivityLogger::class)->record($record, 'deleted');
            });
        });

        DeleteBulkAction::configureUsing(function (DeleteBulkAction $action): void {
            $action->after(function (Collection $records): void {
                foreach ($records as $record) {
                    if ($record instanceof Model) {
                        app(AdminActivityLogger::class)->record($record, 'deleted');
                    }
                }
            });
        });
    }

    /**
     * @param  class-string  $resource
     */
    private function isAuditable(string $resource): bool
    {
        return in_array(LogsAdminActivity::class, class_uses_recursive($resource), true);
    }
}
