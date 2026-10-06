<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

/**
 * Factory for irreversible admin actions that must be confirmed with the
 * current admin password (re-auth).
 *
 * The action is only visible to admins; the password is validated against the
 * authenticated user through Laravel's `current_password` rule, so a wrong or
 * missing password blocks the callback.
 */
final class ReAuthenticateAction
{
    /**
     * @param  Closure(Model|null, array<string, mixed>):void  $callback
     */
    public static function make(string $name, string $label, Closure $callback): Action
    {
        return Action::make($name)
            ->label($label)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription(__('filament.reauth.warning'))
            ->schema([
                TextInput::make('password')
                    ->label(__('filament.reauth.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule('current_password'),
            ])
            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
            ->action(function (array $data, ?Model $record) use ($callback): void {
                $callback($record, $data);
            });
    }
}
