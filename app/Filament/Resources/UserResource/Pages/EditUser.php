<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\AdminActivityLogger;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Role names captured before the Spatie relationship is persisted.
     *
     * @var array<int, string>|null
     */
    private ?array $rolesBefore = null;

    /**
     * Destructive account actions (restore, re-authenticated hard delete) are
     * exposed from the user list to keep the edit page free of irreversible
     * operations.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * `beforeSave` runs before Filament persists the roles relationship, so the
     * prior state can be captured reliably (Spatie roles are not model
     * attributes and never appear in `getOriginal()`).
     */
    protected function beforeSave(): void
    {
        $this->rolesBefore = $this->currentRoleNames();
    }

    /**
     * `afterSave` runs once the roles relationship is persisted, so a role
     * change can be recorded as a dedicated audit action. Role changes are not
     * attributes, therefore the generic update listener never sees them and
     * there is no duplicate entry (R4).
     */
    protected function afterSave(): void
    {
        $rolesBefore = $this->rolesBefore;
        $rolesAfter = $this->currentRoleNames();

        if ($rolesBefore === null || $rolesBefore === $rolesAfter) {
            return;
        }

        app(AdminActivityLogger::class)->record(
            subject: $this->getRecord(),
            verb: 'roles_changed',
            old: ['roles' => $rolesBefore],
            new: ['roles' => $rolesAfter],
        );
    }

    /**
     * @return array<int, string>
     */
    private function currentRoleNames(): array
    {
        /** @var Model $record */
        $record = $this->getRecord();

        return $record->getRelationValue('roles')
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }
}
