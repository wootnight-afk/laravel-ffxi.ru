<?php

namespace App\Observers;

use App\Models\GameCharacter;
use App\Services\CharacterContext;

/**
 * Keeps the active-character pointer consistent with character deletions
 * (STAGE-2 §8).
 *
 * Soft delete does not trigger a foreign key, so the pointer is dropped here.
 * When the model is deleted by GameCharacterSaver the event fires inside the
 * existing transaction and the pointer is removed atomically with `deleted_at`.
 * For any other path (console, admin panel) the observer is an idempotent
 * safety net: it opens no transaction of its own, and if the process dies
 * between the two statements the resolver still returns null because it checks
 * `deleted_at`.
 */
class GameCharacterObserver
{
    public function __construct(private readonly CharacterContext $context) {}

    public function deleting(GameCharacter $character): void
    {
        $this->context->forgetCharacter((int) $character->getKey());
    }
}
