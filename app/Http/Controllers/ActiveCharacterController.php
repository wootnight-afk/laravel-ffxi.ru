<?php

namespace App\Http\Controllers;

use App\Services\CharacterContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Explicit choice of the active character (STAGE-2 §5).
 *
 * Both actions are POST/DELETE only: a GET request can never change the
 * context. Ownership is checked twice — by the policy of the account scope
 * (the query is limited to the characters of the current account) and by
 * CharacterContext::set() under a row lock. A foreign, soft-deleted or missing
 * id is indistinguishable (404); an account that may not hold a character gets
 * 403; a malformed request is a validation error.
 */
class ActiveCharacterController extends Controller
{
    public function store(Request $request, CharacterContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'character_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        if (! $context->isAccountUsable($user)) {
            abort(403);
        }

        // The query is scoped to the account, so a foreign id is simply not
        // found — the response never confirms that the character exists.
        $character = $user->gameCharacters()
            ->whereKey((int) $validated['character_id'])
            ->first();

        if ($character === null) {
            abort(404);
        }

        $context->set($user, $character);

        return redirect()
            ->back(fallback: route('players.dashboard'))
            ->with('status', __('site.players.context.flash.selected'));
    }

    public function destroy(Request $request, CharacterContext $context): RedirectResponse
    {
        $user = $request->user();

        if (! $context->isAccountUsable($user)) {
            abort(403);
        }

        $context->clear($user);

        return redirect()
            ->back(fallback: route('players.dashboard'))
            ->with('status', __('site.players.context.flash.cleared'));
    }
}
