<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Legacy events have `timezone = NULL` and are displayed in UTC only; the
     * form must still start with an explicit value because the field is
     * required (ADR-011 §2.7).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['timezone'] = ($data['timezone'] ?? null) ?: 'UTC';

        return $data;
    }

    /**
     * The UTC shown for a legacy event is a placeholder, not a declaration:
     * saving an unchanged form must keep `timezone = NULL` (ADR-011 §2.7,
     * §8.1) and must not look like a forbidden change on a started event.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof Event
            && $record->timezone === null
            && ($data['timezone'] ?? null) === 'UTC') {
            unset($data['timezone']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        /** @var Event $record */
        return app(EventService::class)->update($actor, $record, $data);
    }

    /**
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
