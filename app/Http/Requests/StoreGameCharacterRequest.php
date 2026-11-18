<?php

namespace App\Http\Requests;

use App\Models\GameCharacter;

/**
 * Validation for creating a character (ADR-012 §2.3).
 *
 * `user_id` is never accepted from the request: the owner is always the
 * authenticated account, so mass assignment cannot move a character to
 * another user. The server must exist and be active.
 */
class StoreGameCharacterRequest extends GameCharacterRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', GameCharacter::class);
    }
}
