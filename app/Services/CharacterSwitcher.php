<?php

namespace App\Services;

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only data of the Players sidebar block (STAGE-2 §6.1).
 *
 * The middleware shares only the active character; the list of characters and
 * the server filter are needed by one partial only, so they are loaded here,
 * once per rendered panel, instead of on every request of the site.
 *
 * The presenter never writes: the filter is read through ServerFilter and the
 * active character through CharacterContext, which is already cached by the
 * middleware for the current request.
 */
class CharacterSwitcher
{
    public function __construct(
        private readonly CharacterContext $context,
        private readonly ServerFilter $filter,
    ) {}

    /**
     * @return array{
     *     characters: Collection<int, GameCharacter>,
     *     active: GameCharacter|null,
     *     servers: Collection<int, GameServer>,
     *     filter: GameServer|null,
     *     mismatch: bool
     * }
     */
    public function data(?User $user): array
    {
        $active = $this->context->for($user);
        $filterServer = $this->filter->server();

        return [
            'characters' => $this->characters($user),
            'active' => $active,
            'servers' => GameServer::query()->active()->ordered()->get(),
            'filter' => $filterServer,
            'mismatch' => $active !== null
                && $filterServer !== null
                && (int) $active->game_server_id !== (int) $filterServer->getKey(),
        ];
    }

    /**
     * Characters of the account in the same order as the cabinet list.
     *
     * @return Collection<int, GameCharacter>
     */
    private function characters(?User $user): Collection
    {
        if ($user === null) {
            return new Collection;
        }

        return $user->gameCharacters()
            ->with('server')
            ->get()
            ->sortBy(fn (GameCharacter $character): string => sprintf(
                '%05d-%s-%s',
                (int) $character->server?->sort_order,
                mb_strtolower((string) $character->server?->name),
                mb_strtolower($character->name),
            ))
            ->values();
    }
}
