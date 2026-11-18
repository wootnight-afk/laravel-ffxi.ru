<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\GameCharacter;
use App\Models\User;
use App\Models\UserActiveCharacter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * The single source of the active character of an account (ADR-013 §2.3).
 *
 * Nothing else — no controller, view or Livewire component — may resolve the
 * active character on its own: every caller asks this service, so the rules
 * live in one place and the middleware can share the same value.
 *
 * Rules:
 *  - a guest has no context;
 *  - a missing pointer, a soft-deleted character or an inconsistent pointer
 *    (which the schema already prevents) resolve to null;
 *  - an account that is not usable — not Active, banned, deletion requested or
 *    suspended — has no context: the pointer is kept in the database, but the
 *    character is not exposed and cannot be switched to;
 *  - read methods never write: a stale pointer is ignored, not repaired;
 *  - the result is cached per request, so one page resolves it once.
 */
class CharacterContext
{
    /**
     * Resolved context per account id. `null` is a valid cached value and is
     * distinguished from "not resolved yet" with array_key_exists().
     *
     * @var array<int, GameCharacter|null>
     */
    private array $resolved = [];

    /**
     * Active character of the account, or null when the account has none.
     */
    public function for(?User $user): ?GameCharacter
    {
        if ($user === null) {
            return null;
        }

        $key = (int) $user->getKey();

        if (! array_key_exists($key, $this->resolved)) {
            $this->resolved[$key] = $this->resolve($user);
        }

        return $this->resolved[$key];
    }

    /**
     * Whether the account may hold and switch an active character.
     *
     * The project has no separate "banned" status: a ban is `banned_until` in
     * the future. `isProfilePublic()` is deliberately not used — it describes
     * the visibility of the profile, not the right to act.
     */
    public function isAccountUsable(User $user): bool
    {
        return $user->status === UserStatus::Active
            && ! $user->isBanned()
            && ! $user->isDeletionRequested()
            && ! $user->isSuspended();
    }

    /**
     * Store the explicit choice of the account.
     *
     * Ownership is checked under a row lock and the pointer is written with a
     * single atomic upsert on the primary key, so two tabs can never produce
     * two rows: the last explicit action wins. The composite foreign key backs
     * the same rule in the schema.
     *
     * @throws ModelNotFoundException when the character does not exist, is
     *                                soft-deleted or belongs to another account
     */
    public function set(User $user, GameCharacter $character): void
    {
        DB::transaction(function () use ($user, $character): void {
            $locked = GameCharacter::query()
                ->whereKey($character->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || (int) $locked->user_id !== (int) $user->getKey()) {
                throw (new ModelNotFoundException)->setModel(GameCharacter::class);
            }

            $now = now();

            UserActiveCharacter::query()->upsert(
                [[
                    'user_id' => (int) $user->getKey(),
                    'character_id' => (int) $locked->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['user_id'],
                ['character_id', 'updated_at'],
            );
        });

        $this->refresh($user);
    }

    /**
     * Drop the explicit choice of the account.
     */
    public function clear(User $user): void
    {
        UserActiveCharacter::query()
            ->whereKey($user->getKey())
            ->delete();

        $this->refresh($user);
    }

    /**
     * Drop a pointer that names the given character.
     *
     * Called from the delete transaction of GameCharacterSaver and from the
     * model observer. It is idempotent and opens no transaction of its own, so
     * it joins whatever transaction the caller already owns.
     */
    public function forgetCharacter(int $characterId): void
    {
        UserActiveCharacter::query()
            ->where('character_id', $characterId)
            ->delete();

        $this->resolved = [];
    }

    /**
     * Forget the cached value of one account or of every account.
     */
    public function refresh(?User $user = null): void
    {
        if ($user === null) {
            $this->resolved = [];

            return;
        }

        unset($this->resolved[(int) $user->getKey()]);
    }

    private function resolve(User $user): ?GameCharacter
    {
        if (! $this->isAccountUsable($user)) {
            return null;
        }

        $pointer = UserActiveCharacter::query()
            ->with('character.server')
            ->whereKey($user->getKey())
            ->first();

        $character = $pointer?->character;

        if ($character === null) {
            return null;
        }

        // Defence in depth: the schema already forbids a foreign character.
        if ((int) $character->user_id !== (int) $user->getKey()) {
            return null;
        }

        return $character;
    }
}
