<?php

namespace App\Http\Requests;

use App\Models\GameCharacter;

/**
 * Validation for editing a character (ADR-012 §2.3).
 *
 * The owner is taken from the route-bound character, never from the request.
 * The name uniqueness check ignores the character being edited and is scoped
 * to the target server.
 */
class UpdateGameCharacterRequest extends GameCharacterRequest
{
    public function authorize(): bool
    {
        $character = $this->route('character');

        return $character instanceof GameCharacter
            && $this->user() !== null
            && $this->user()->can('update', $character);
    }
}
