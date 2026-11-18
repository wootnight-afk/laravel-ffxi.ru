<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Services\LegacyGameDataImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manual transfer of legacy account game data when the account has several
 * characters (STAGE-1 §6.4): the owner explicitly chooses the character, an
 * automatic assignment to the first one is never performed.
 */
class LegacyGameDataController extends Controller
{
    public function __construct(
        private readonly LegacyGameDataImporter $importer,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'character_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        $character = $user->gameCharacters()->whereKey((int) $data['character_id'])->first();

        if ($character === null) {
            abort(404);
        }

        $this->importer->transferTo($user, $character);

        return redirect()
            ->route('cabinet.characters.index')
            ->with('status', __('site.cabinet.characters.legacy.flash.transferred'));
    }
}
