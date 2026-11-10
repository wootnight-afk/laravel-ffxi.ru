<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Schema;

/**
 * Admin panel profile.
 *
 * The dashboard region clock (`users.secondary_timezone`) is managed inline in
 * `ClockWidget` (ADR-011 §2.25); the profile deliberately does not offer a
 * second, competing interface for the same setting.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
