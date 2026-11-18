<?php

namespace App\Policies;

use App\Models\GameCharacter;
use App\Models\User;

/**
 * Character ownership policy (ADR-012 §2.4).
 *
 * Every action is checked on the server: hiding a button in the UI is never
 * the access control. The owner of the account is the only non-admin who may
 * read, edit or delete a character.
 */
class GameCharacterPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GameCharacter $character): bool
    {
        return $this->owns($user, $character);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && ! $user->isBanned();
    }

    public function update(User $user, GameCharacter $character): bool
    {
        return $this->owns($user, $character);
    }

    public function delete(User $user, GameCharacter $character): bool
    {
        return $this->owns($user, $character);
    }

    private function owns(User $user, GameCharacter $character): bool
    {
        return $character->user_id === $user->id;
    }
}
