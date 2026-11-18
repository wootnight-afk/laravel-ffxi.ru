<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * "My characters" page of the Players section (ADR-012 §2.4).
 *
 * Every action is authorized through GameCharacterPolicy; the route-bound
 * character is resolved with implicit binding, so a foreign id yields 403
 * instead of leaking another account's data.
 */
class MyCharactersController extends Controller
{
    /**
     * Retained only as a redirect stub: character management moved to the
     * cabinet (STAGE-1 §2.1). No write path lives here any more.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('cabinet.characters.index');
    }
}
