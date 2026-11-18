<?php

namespace App\Services;

use App\Models\GameServer;

/**
 * Independent game-server filter of the Players directory (STAGE-2 §6.1).
 *
 * The filter is a viewing preference of the catalogue, not a property of the
 * account: it lives in the session and never reads or writes the active
 * character. The active character lives in the database and never learns about
 * the filter. A mismatch between the two is only displayed in the interface.
 *
 * The chosen server is stored as a slug. A slug that no longer belongs to an
 * active server is ignored (treated as "all servers") and is never rewritten by
 * a GET request — the stored value stays until the user changes it.
 */
class ServerFilter
{
    public const SESSION_KEY = 'players.server_filter';

    private ?GameServer $resolved = null;

    private bool $isResolved = false;

    /**
     * Stored slug without any validation.
     */
    public function slug(): ?string
    {
        $slug = session(self::SESSION_KEY);

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * Active server selected by the filter, or null for "all servers".
     *
     * A deactivated or unknown server resolves to null: the listing falls back
     * to the unfiltered query without touching the session.
     */
    public function server(): ?GameServer
    {
        if ($this->isResolved) {
            return $this->resolved;
        }

        $slug = $this->slug();

        $this->resolved = $slug === null
            ? null
            : GameServer::query()->active()->where('slug', $slug)->first();
        $this->isResolved = true;

        return $this->resolved;
    }

    /**
     * Whether the slug names an active server.
     */
    public function accepts(string $slug): bool
    {
        return GameServer::query()->active()->where('slug', $slug)->exists();
    }

    /**
     * Store the choice; null means "all servers".
     */
    public function set(?string $slug): void
    {
        if ($slug === null || $slug === '') {
            session()->forget(self::SESSION_KEY);
        } else {
            session([self::SESSION_KEY => $slug]);
        }

        $this->resolved = null;
        $this->isResolved = false;
    }
}
